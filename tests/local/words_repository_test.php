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
 * Unit tests for words_repository.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Tests for words_repository — requires database.
 */
final class words_repository_test extends \advanced_testcase {
    /** @var \stdClass Shared user for addedby FK. */
    private \stdClass $user;

    /** @var \stdClass Shared course for playerwords FK. */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->course = $this->getDataGenerator()->create_course();
        $this->user   = $this->getDataGenerator()->create_user();
    }

    /**
     * Inserts a playerwords instance record and returns a minimal stdClass for pick_round_word.
     *
     * @param int $wordmode Word selection mode constant.
     * @param int $min      Minimum word length.
     * @param int $max      Maximum word length.
     * @return \stdClass
     */
    private function make_instance(
        int $wordmode = PLAYERWORDS_WORDMODE_RANDOM,
        int $min = 4,
        int $max = 6
    ): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('playerwords', (object)[
            'course'              => $this->course->id,
            'name'               => 'Test',
            'intro'              => '',
            'introformat'        => 0,
            'sources'            => 1,
            'glossaryid'         => 0,
            'min_length'         => $min,
            'max_length'         => $max,
            'max_attempts'       => 6,
            'timer_seconds'      => 0,
            'show_ranking'       => 1,
            'wordmode'           => $wordmode,
            'max_rounds'         => 0,
            'cooldown_seconds'   => 0,
            'completionattempts' => 0,
            'grade'              => 100,
            'gradepass'          => 0,
            'grademethod'        => 1,
            'hud_round_cost_item' => 0,
            'hud_round_cost_qty'  => 1,
            'hud_hint_cost_item'  => 0,
            'hud_hint_cost_qty'   => 1,
            'timecreated'        => $now,
            'timemodified'       => $now,
        ]);
        return (object)['id' => $id, 'min_length' => $min, 'max_length' => $max, 'wordmode' => $wordmode];
    }

    /**
     * Inserts one word record for the given instance.
     *
     * @param int    $instanceid Activity instance id.
     * @param string $word       Word text.
     * @param int    $approved   Approval status (1 = approved, 0 = pending).
     * @return void
     */
    private function make_word(int $instanceid, string $word, int $approved = 1): void {
        global $DB;
        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instanceid,
            'word'          => $word,
            'concept'       => $word,
            'hint'          => '',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => $approved,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);
    }

    /**
     * Tests that pick_round_word returns null when no words exist.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_returns_null_when_empty(): void {
        $instance = $this->make_instance();
        $this->assertNull(words_repository::pick_round_word($instance));
    }

    /**
     * Tests that unapproved words are excluded from picks.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_excludes_unapproved(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'gato', 0);
        $this->assertNull(words_repository::pick_round_word($instance));
    }

    /**
     * Tests that words shorter than min_length are not returned.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_excludes_too_short(): void {
        $instance = $this->make_instance(PLAYERWORDS_WORDMODE_RANDOM, 4, 6);
        $this->make_word($instance->id, 'gat');
        $this->assertNull(words_repository::pick_round_word($instance));
    }

    /**
     * Tests that words longer than max_length are not returned.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_excludes_too_long(): void {
        $instance = $this->make_instance(PLAYERWORDS_WORDMODE_RANDOM, 4, 6);
        $this->make_word($instance->id, 'semaforo');
        $this->assertNull(words_repository::pick_round_word($instance));
    }

    /**
     * Tests that an approved word within length bounds is returned in random mode.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_random_returns_approved_word(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'gato');
        $word = words_repository::pick_round_word($instance);
        $this->assertNotNull($word);
        $this->assertSame('gato', $word->word);
    }

    /**
     * Tests that shared mode is deterministic: same round always picks the same word.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_shared_is_deterministic(): void {
        $instance = $this->make_instance(PLAYERWORDS_WORDMODE_SHARED);
        $this->make_word($instance->id, 'gato');
        $this->make_word($instance->id, 'mesa');
        $this->make_word($instance->id, 'bola');

        $round0a = words_repository::pick_round_word($instance, 0);
        $round0b = words_repository::pick_round_word($instance, 0);
        $this->assertNotNull($round0a);
        $this->assertSame($round0a->word, $round0b->word);
    }

    /**
     * Tests that shared mode cycles: round N and round (N + count) return the same word.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_shared_cycles(): void {
        $instance = $this->make_instance(PLAYERWORDS_WORDMODE_SHARED);
        $this->make_word($instance->id, 'gato');
        $this->make_word($instance->id, 'mesa');
        $this->make_word($instance->id, 'bola');

        $round0 = words_repository::pick_round_word($instance, 0);
        $round3 = words_repository::pick_round_word($instance, 3);
        $this->assertNotNull($round0);
        $this->assertSame($round0->word, $round3->word);
    }

    /**
     * Tests that words containing non-letter characters are excluded.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_excludes_non_letter_chars(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'ga to');
        $this->assertNull(words_repository::pick_round_word($instance));
    }
}
