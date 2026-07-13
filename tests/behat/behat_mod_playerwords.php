<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Step definitions for mod_playerwords Behat tests.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing

use Behat\Gherkin\Node\TableNode;

/**
 * Custom Behat step definitions for the PlayerWords activity.
 */
class behat_mod_playerwords extends behat_base {
    /**
     * Seeds approved manual words directly into an instance's pool.
     *
     * Bypasses managewords.php and the approval flow, so a scenario can rely on a
     * deterministic pool (e.g. a single word within the instance's length bounds) instead
     * of the pseudo-random round selection.
     *
     * @param string $activityname PlayerWords activity name.
     * @param TableNode $data Table with a required "word" column and an optional "hint" one.
     * @Given the following PlayerWords words exist in activity :activityname:
     */
    public function the_following_playerwords_words_exist_in_activity(string $activityname, TableNode $data): void {
        $playerwordsid = $this->get_playerwords_id($activityname);
        $generator = behat_util::get_data_generator()->get_plugin_generator('mod_playerwords');

        foreach ($data->getHash() as $row) {
            $generator->create_word($playerwordsid, $row['word'], $row['hint'] ?? '');
        }
    }

    /**
     * Seeds finished attempt rows directly, without playing a real round.
     *
     * Used to fill the teacher attempt report or the ranking with enough rows to exercise
     * pagination, sorting and tie-breaking — driving dozens of real rounds through the UI
     * would be slow and flaky. Rows without an explicit "created" column get a strictly
     * decreasing timestamp (one minute apart, in table order) so "sort by date" scenarios
     * have a deterministic order to assert against.
     *
     * @param string $activityname PlayerWords activity name.
     * @param TableNode $data Table with columns: user, word, attemptsused, timeused,
     *     completed, score, rankingpoints, created (all but user and word are optional).
     * @Given the following PlayerWords attempts exist in activity :activityname:
     */
    public function the_following_playerwords_attempts_exist_in_activity(string $activityname, TableNode $data): void {
        global $DB;

        $playerwordsid = $this->get_playerwords_id($activityname);
        $generator = behat_util::get_data_generator()->get_plugin_generator('mod_playerwords');
        $now = time();

        foreach ($data->getHash() as $index => $row) {
            $userid = $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $wordid = $DB->get_field_sql(
                'SELECT id FROM {playerwords_words} WHERE playerwordsid = :pwid AND word = :word',
                ['pwid' => $playerwordsid, 'word' => $row['word']],
                MUST_EXIST
            );

            $columnmap = [
                'attemptsused'  => 'attempts_used',
                'timeused'      => 'time_used',
                'completed'     => 'completed',
                'score'         => 'score',
                'rankingpoints' => 'rankingpoints',
            ];
            $overrides = [];
            foreach ($columnmap as $column => $field) {
                if (isset($row[$column]) && $row[$column] !== '') {
                    $overrides[$field] = $row[$column];
                }
            }
            $created = (isset($row['created']) && $row['created'] !== '') ? (int) $row['created'] : ($now - $index * 60);
            $overrides['timecreated'] = $created;
            $overrides['timefinished'] = $created;

            $generator->create_attempt($playerwordsid, (int) $userid, (int) $wordid, $overrides);
        }
    }

    /**
     * Seeds many identical finished attempt rows for one student, for pagination scenarios.
     *
     * Writing 31 rows by hand in a Gherkin table just to cross the 30-per-page boundary
     * would be unreadable noise — this generates them instead, one second apart in table
     * order (newest first, matching the report's default date-descending sort).
     *
     * @param int $count Number of attempts to create.
     * @param string $username Student username.
     * @param string $word Word text; must already exist in the activity's pool.
     * @param string $activityname PlayerWords activity name.
     * @Given :count PlayerWords attempts exist for :username with word :word in activity :activityname
     */
    public function n_playerwords_attempts_exist_for_user(
        int $count,
        string $username,
        string $word,
        string $activityname
    ): void {
        global $DB;

        $playerwordsid = $this->get_playerwords_id($activityname);
        $generator = behat_util::get_data_generator()->get_plugin_generator('mod_playerwords');
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $wordid = $DB->get_field_sql(
            'SELECT id FROM {playerwords_words} WHERE playerwordsid = :pwid AND word = :word',
            ['pwid' => $playerwordsid, 'word' => $word],
            MUST_EXIST
        );
        $now = time();

        for ($i = 0; $i < $count; $i++) {
            $created = $now - $i;
            $generator->create_attempt($playerwordsid, (int) $userid, (int) $wordid, [
                'timecreated'  => $created,
                'timefinished' => $created,
            ]);
        }
    }

    /**
     * Overrides timer_seconds or cooldown_seconds directly in the database.
     *
     * playerwords_add_instance() always recomputes both columns from the transient
     * timer_minutes / cooldown_amount+cooldown_unit form fields (see lib.php), ignoring
     * any value passed straight through the generic "activities exist" step — the same
     * reason the PHPUnit suite works around it this way instead (see round_service_test.php,
     * submit_guess_test.php). timer_minutes-only granularity cannot express a short enough
     * timer for a fast Behat scenario, so this is the only way to set an exact value.
     *
     * @param string $activityname PlayerWords activity name.
     * @param string $field Either "timer_seconds" or "cooldown_seconds".
     * @param int $seconds Value to store.
     * @Given the PlayerWords activity :activityname has :field set to :seconds seconds
     */
    public function the_playerwords_activity_has_field_set_to_seconds(
        string $activityname,
        string $field,
        int $seconds
    ): void {
        global $DB;

        if ($field !== 'timer_seconds' && $field !== 'cooldown_seconds') {
            throw new \coding_exception('Unsupported field: ' . $field);
        }

        $playerwordsid = $this->get_playerwords_id($activityname);
        $DB->set_field('playerwords', $field, $seconds, ['id' => $playerwordsid]);
    }

    /**
     * Marks a user as having already seen the automatic how-to-play introduction
     * (see mod_playerwords\local\intro_service), so scenarios about anything else are
     * not incidentally interrupted by the modal opening on its own on a fresh user's
     * first visit to any PlayerWords activity — a precondition for the scenario, not
     * the thing under test, same reasoning already used for PlayerHUD items below.
     * The auto-show behaviour itself has its own dedicated scenario in
     * mod_playerwords_toolbar.feature, using a user this step is never applied to.
     *
     * @param string $username Moodle username.
     * @Given :username has already seen the playerwords intro
     */
    public function user_has_already_seen_the_playerwords_intro(string $username): void {
        global $DB;

        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        \mod_playerwords\local\intro_service::mark_intro_seen($userid);
    }

    /**
     * Asserts that the given CSS element has the disabled attribute.
     *
     * Custom rather than relying on core's own "should be disabled" step: that step is not
     * defined on every Moodle version this plugin supports (confirmed missing on 4.5/5.0/5.2
     * for block_playerhud, same ecosystem) — matches the pattern already established there.
     *
     * @param string $selector CSS selector.
     * @Then the :selector element should be disabled
     */
    public function element_is_disabled(string $selector): void {
        $node = $this->find('css', $selector);
        if (!$node->hasAttribute('disabled')) {
            throw new \Exception("Element '{$selector}' is expected to be disabled but is not.");
        }
    }

    /**
     * Creates a PlayerHUD item in the block already added to the given course.
     *
     * Direct $DB insert rather than going through the block's own management UI, matching
     * the pattern already established in behat_block_playerhud.php for the same reason:
     * the item's existence is a precondition for the scenario, not the thing under test.
     *
     * @param string $itemname Display name for the item.
     * @param string $shortname Course shortname. The PlayerHUD block must already be added.
     * @Given a PlayerHUD item :itemname exists in course :shortname
     */
    public function a_playerhud_item_exists_in_course(string $itemname, string $shortname): void {
        global $DB;

        $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->get_playerhud_block_id($shortname),
            'name'            => $itemname,
            'description'     => '',
            'image'           => '🔑',
            'xp'              => 10,
            'secret'          => 0,
            'enabled'         => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Wires an existing PlayerHUD item as the item cost to start a round.
     *
     * @param string $activityname PlayerWords activity name.
     * @param int $qty Quantity required.
     * @param string $itemname PlayerHUD item name; must already exist in the activity's course.
     * @Given the PlayerWords activity :activityname charges :qty PlayerHUD item :itemname to start a round
     */
    public function playerwords_activity_charges_item_to_start(string $activityname, int $qty, string $itemname): void {
        $this->set_playerwords_hud_field($activityname, $itemname, 'hud_round_cost_item', 'hud_round_cost_qty', $qty);
    }

    /**
     * Wires an existing PlayerHUD item as the item cost to reveal the hint.
     *
     * @param string $activityname PlayerWords activity name.
     * @param int $qty Quantity required.
     * @param string $itemname PlayerHUD item name; must already exist in the activity's course.
     * @Given the PlayerWords activity :activityname charges :qty PlayerHUD item :itemname to reveal the hint
     */
    public function playerwords_activity_charges_item_for_hint(string $activityname, int $qty, string $itemname): void {
        $this->set_playerwords_hud_field($activityname, $itemname, 'hud_hint_cost_item', 'hud_hint_cost_qty', $qty);
    }

    /**
     * Wires an existing PlayerHUD item as the reward for winning a round.
     *
     * @param string $activityname PlayerWords activity name.
     * @param int $qty Quantity granted.
     * @param string $itemname PlayerHUD item name; must already exist in the activity's course.
     * @Given the PlayerWords activity :activityname grants :qty PlayerHUD item :itemname for winning a round
     */
    public function playerwords_activity_grants_item_on_win(string $activityname, int $qty, string $itemname): void {
        $this->set_playerwords_hud_field($activityname, $itemname, 'hud_win_grant_item', 'hud_win_grant_qty', $qty);
    }

    /**
     * Grants a user a starting balance of a PlayerHUD item, bypassing external_items::grant()
     * (and therefore any XP side effect) since the scenario only cares about the balance
     * itself, not how it was acquired — mirrors block_playerhud's own inventory-seeding step.
     *
     * @param string $username Moodle username.
     * @param int $qty Quantity to grant.
     * @param string $itemname PlayerHUD item name.
     * @param string $shortname Course shortname.
     * @Given :username has :qty PlayerHUD item :itemname in course :shortname
     */
    public function user_has_playerhud_item(string $username, int $qty, string $itemname, string $shortname): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $blockinstanceid = $this->get_playerhud_block_id($shortname);
        $itemid = $DB->get_field(
            'block_playerhud_items',
            'id',
            ['blockinstanceid' => $blockinstanceid, 'name' => $itemname],
            MUST_EXIST
        );

        for ($i = 0; $i < $qty; $i++) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid'      => $userid,
                'itemid'      => $itemid,
                'dropid'      => 0,
                'source'      => 'behat',
                'timecreated' => time(),
                'xpawarded'   => 0,
            ]);
        }
    }

    /**
     * Shared setter for the three hud_*_item/hud_*_qty column pairs.
     *
     * @param string $activityname PlayerWords activity name.
     * @param string $itemname PlayerHUD item name; resolved within the activity's own course.
     * @param string $itemfield Column name for the item id (e.g. hud_round_cost_item).
     * @param string $qtyfield Column name for the quantity (e.g. hud_round_cost_qty).
     * @param int $qty Quantity value to store.
     */
    private function set_playerwords_hud_field(
        string $activityname,
        string $itemname,
        string $itemfield,
        string $qtyfield,
        int $qty
    ): void {
        global $DB;

        $playerwordsid = $this->get_playerwords_id($activityname);
        $courseid = (int) $DB->get_field('playerwords', 'course', ['id' => $playerwordsid], MUST_EXIST);
        $shortname = (string) $DB->get_field('course', 'shortname', ['id' => $courseid], MUST_EXIST);
        $blockinstanceid = $this->get_playerhud_block_id($shortname);
        $itemid = $DB->get_field(
            'block_playerhud_items',
            'id',
            ['blockinstanceid' => $blockinstanceid, 'name' => $itemname],
            MUST_EXIST
        );

        $DB->set_field('playerwords', $itemfield, $itemid, ['id' => $playerwordsid]);
        $DB->set_field('playerwords', $qtyfield, $qty, ['id' => $playerwordsid]);
    }

    /**
     * Resolves the PlayerHUD block instance id for a course. The block must already have
     * been added (e.g. via "I add the PlayerHUD block" while editing the course).
     *
     * @param string $shortname Course shortname.
     * @return int
     */
    private function get_playerhud_block_id(string $shortname): int {
        global $DB;

        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $context = \context_course::instance($courseid);

        return (int) $DB->get_field('block_instances', 'id', [
            'blockname'       => 'playerhud',
            'parentcontextid' => $context->id,
        ], MUST_EXIST);
    }

    /**
     * Resolves a PlayerWords activity name to its instance id.
     *
     * @param string $activityname Activity name as configured in the instance.
     * @return int
     */
    private function get_playerwords_id(string $activityname): int {
        global $DB;

        return (int) $DB->get_field('playerwords', 'id', ['name' => $activityname], MUST_EXIST);
    }
}
