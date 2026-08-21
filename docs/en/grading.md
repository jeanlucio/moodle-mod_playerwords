# 🧮 Grading & Ranking

PlayerWords computes a **grade** and a **ranking** total from the same finished rounds, but the
two are configured completely independently — a teacher can keep the grade simple while still
rewarding efficient play in the ranking, or the other way around.

**Both are entirely optional, and each is switched on or off on its own:**

* **Grade:** leave the standard `Grade` field set to *None* to run the activity fully ungraded —
  no grade is ever computed or written to the gradebook, and the `Grading method` / `Grade
  scoring` settings disappear from the form.
* **Ranking:** leave `Show ranking` set to *No* to hide the ranking everywhere — in-game, on the
  dedicated ranking page, and the extra column in the attempt history — and the `Ranking scoring`
  setting disappears from the form too.

Turning one off never affects the other: an activity can be graded with no ranking, ranked with
no grade, both, or neither.

**Per-round scoring** decides how much a single round is worth, chosen separately for the grade
and for the ranking (`Grade scoring` / `Ranking scoring` settings, both default to **Binary**).
The grade is scored against the activity's **own configured maximum grade**; the **ranking always
uses its own fixed 100-point base, completely independent of the grade** — even when the activity
has no grade at all (`Grade` = *None*, the form's default), the ranking still works normally:

| Mode | A won round is worth... | A lost, forfeited, or timed-out round |
|---|---|---|
| **Binary** (default) | The full base (the activity's grade, or a fixed 100 points for ranking) | Zero |
| **Linear** | Full credit on the first two attempts, then a share proportional to attempts spared: `base × (max_attempts − attempts_used + 1) / (max_attempts − 1)` | Zero |

Linear gives full credit on the first two attempts — a confident second guess is not treated as
less deserving than a first-try one, since this is a non-punitive educational game rather than a
luck-based guessing contest — then scales the remaining attempts down proportionally, never fully
zeroing out a win: even winning on the very last allowed attempt still earns a positive share.
Example with 6 maximum attempts — the grade column assumes a 100-point maximum grade, but the
ranking column is exactly this in **any** activity, even one with no grade configured at all:

| Attempts used | Grade (100-point base) | Ranking (100-point base, always) |
|---:|---:|---:|
| 1 | 100.00 | 100.00 |
| 2 | 100.00 | 100.00 |
| 3 | 80.00 | 80.00 |
| 4 | 60.00 | 60.00 |
| 5 | 40.00 | 40.00 |
| 6 | 20.00 | 20.00 |
| Not completed | 0.00 | 0.00 |

With `Maximum attempts` set to 2 or fewer, Linear is numerically identical to Binary — every
allowed attempt already falls within the full-credit plateau.

**Combining several rounds into one final grade** is a separate setting, `Grading method`
(highest grade, average grade, first attempt, last attempt, or average over all required rounds).
It works the same regardless of whether the per-round scoring above is Binary or Linear: it only
ever aggregates whatever value each round already recorded.

**The ranking** is the sum of every finished round's ranking points for a student (`SUM`),
ordered highest first; ties are broken by fewer attempts used on average, then less time spent on
average. It only appears when the teacher enables "Show ranking", and never reveals a round still
in progress.

**Only the top 5 are shown — deliberately, not a bug:** both the in-game ranking widget and the
dedicated ranking page cap the list at 5 rows, to avoid publicly ranking every student in the
class. A student ranked lower still sees exactly where they stand: an extra row, separated by
"…", shows their own real position and score, without exposing anyone else's rank below 5th.
Anyone who can manage the activity (editingteacher, manager) never appears in the ranking at all,
even if they play the activity themselves — the same way their own attempts are excluded from the
attempt report below.

**"Show ranking" only controls visibility, not data collection:** ranking points are computed and
stored for every finished round regardless of whether the setting is on or off at the time.
Turning it on after students have already played reveals the full total accumulated since the
activity started, not just the points earned from that moment forward — nothing is lost, and
nothing needs to be "recovered" by switching it off and back on.

**Locked once graded:** the moment the activity records a real grade for any student, `Maximum
attempts` and `Grade scoring` lock — the same way Moodle already locks a graded activity's own
"Maximum grade" field once real grades exist. This guarantees every round ever recorded for that
activity was scored under the exact same grade rules for the activity's whole lifetime.

**`Ranking scoring` locks separately, the moment any finished attempt exists** — it doesn't wait
for a real grade, because ranking points are already computed and stored for every finished round
regardless of whether `Grade` or `Show ranking` are even on (see above). An entirely ungraded,
ranking-only activity already accumulates real history from its very first round; locking the
scoring mode once that history exists prevents the same scale inconsistency the lock above
prevents for the grade.

**Attempt history:** each student can review their own past rounds — word, attempts used, time,
grade score and (when ranking is enabled) ranking points — on a dedicated toolbar page. Whoever
can manage the activity sees that same page too, including their own attempts if they have
played the activity. The all-students report lives on a separate page, visible only to whoever
can manage the activity: one table with every student's attempts, 30 rows per page, sortable by
clicking any column header, and filterable to a single student. Like the ranking, that report
never includes a manager's own attempts.

**Deleting attempts reverses these locks when they no longer apply.** From the all-students
report, whoever can manage the activity can delete a student's attempts individually or in bulk.
Deleting genuinely clears that student's grade rather than leaving a stale value behind, so if
every attempt for every student is removed, `Maximum attempts` and `Grade scoring` unlock again
exactly as `grade_item::has_grades()` expects — the same way they would on an activity that was
never played at all. `Ranking scoring` follows the same logic against the attempts table
directly, independent of whether grading is even on.
