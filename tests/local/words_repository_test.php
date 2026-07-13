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
     * Tests that has_cedilla_word is false when no word contains one.
     *
     * @covers \mod_playerwords\local\words_repository::has_cedilla_word
     * @return void
     */
    public function test_has_cedilla_word_false_when_absent(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'gato');
        $this->assertFalse(words_repository::has_cedilla_word($instance->id));
    }

    /**
     * Tests that has_cedilla_word is true once any approved word contains one.
     *
     * @covers \mod_playerwords\local\words_repository::has_cedilla_word
     * @return void
     */
    public function test_has_cedilla_word_true_when_present(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'gato');
        $this->make_word($instance->id, 'cabeça');
        $this->assertTrue(words_repository::has_cedilla_word($instance->id));
    }

    /**
     * Tests that has_cedilla_word ignores unapproved (pending) words.
     *
     * @covers \mod_playerwords\local\words_repository::has_cedilla_word
     * @return void
     */
    public function test_has_cedilla_word_ignores_unapproved(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'cabeça', 0);
        $this->assertFalse(words_repository::has_cedilla_word($instance->id));
    }

    /**
     * Tests that has_cedilla_word is scoped to its own activity, never a sibling one.
     *
     * @covers \mod_playerwords\local\words_repository::has_cedilla_word
     * @return void
     */
    public function test_has_cedilla_word_is_scoped_to_its_own_activity(): void {
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $this->make_word($instanceb->id, 'cabeça');
        $this->assertFalse(words_repository::has_cedilla_word($instancea->id));
        $this->assertTrue(words_repository::has_cedilla_word($instanceb->id));
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

    /**
     * In random mode, the excluded word id is never picked while another candidate
     * is available — repeated calls always land on the other word.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_random_avoids_excluded_word_when_alternative_exists(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'gato');
        $this->make_word($instance->id, 'mesa');
        $excludeid = (int)$DB->get_field('playerwords_words', 'id', ['word' => 'gato'], MUST_EXIST);

        for ($i = 0; $i < 10; $i++) {
            $word = words_repository::pick_round_word($instance, 0, $excludeid);
            $this->assertNotNull($word);
            $this->assertSame('mesa', $word->word);
        }
    }

    /**
     * When the excluded word is the only candidate left, it is allowed back in rather
     * than blocking play — the same graceful fallback local_playergames' quiz_loader
     * uses when its "already seen" pool is exhausted.
     *
     * @covers \mod_playerwords\local\words_repository::pick_round_word
     * @return void
     */
    public function test_pick_word_random_allows_excluded_word_when_it_is_the_only_candidate(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'gato');
        $excludeid = (int)$DB->get_field('playerwords_words', 'id', ['word' => 'gato'], MUST_EXIST);

        $word = words_repository::pick_round_word($instance, 0, $excludeid);

        $this->assertNotNull($word);
        $this->assertSame('gato', $word->word);
    }

    /**
     * get_last_played_word_id() returns 0 when the student has no finished rounds yet.
     *
     * @covers \mod_playerwords\local\words_repository::get_last_played_word_id
     * @return void
     */
    public function test_get_last_played_word_id_none_yet(): void {
        $instance = $this->make_instance();
        $this->assertSame(0, words_repository::get_last_played_word_id($instance, $this->user->id));
    }

    /**
     * get_last_played_word_id() returns the word from the most recently finished round,
     * ignoring a still-pending reservation (timefinished = 0) for a later word.
     *
     * @covers \mod_playerwords\local\words_repository::get_last_played_word_id
     * @return void
     */
    public function test_get_last_played_word_id_ignores_pending_reservation(): void {
        global $DB;
        $instance = $this->make_instance();

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 11,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time() - 60,
            'timefinished'  => time() - 60,
        ]);
        // A pending reservation for a later word — not a finished round yet.
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 22,
            'attempts_used' => 0,
            'time_used'     => 0,
            'completed'     => 0,
            'score'         => 0,
            'timecreated'   => time(),
            'timefinished'  => 0,
        ]);

        $this->assertSame(11, words_repository::get_last_played_word_id($instance, $this->user->id));
    }

    /**
     * Inserts a full playerwords instance record, with every column populated, for
     * tests that need fields beyond the minimal set make_instance() returns (e.g.
     * ->sources, ->glossaryid, ->course, used by sync_glossary_words()).
     *
     * @param array $overrides Field overrides.
     * @return \stdClass Full instance record.
     */
    private function make_full_instance(array $overrides = []): \stdClass {
        global $DB;
        $now = time();
        $defaults = [
            'course'              => $this->course->id,
            'name'                => 'Test',
            'intro'               => '',
            'introformat'         => 0,
            'sources'             => PLAYERWORDS_SOURCE_MANUAL | PLAYERWORDS_SOURCE_GLOSSARY,
            'glossaryid'          => 0,
            'min_length'          => 1,
            'max_length'          => 30,
            'max_attempts'        => 6,
            'timer_seconds'       => 0,
            'show_ranking'        => 1,
            'wordmode'            => PLAYERWORDS_WORDMODE_RANDOM,
            'max_rounds'          => 0,
            'cooldown_seconds'    => 0,
            'completionattempts'  => 0,
            'grade'               => 100,
            'gradepass'           => 0,
            'grademethod'         => 1,
            'hud_round_cost_item' => 0,
            'hud_round_cost_qty'  => 1,
            'hud_hint_cost_item'  => 0,
            'hud_hint_cost_qty'   => 1,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ];
        $id = $DB->insert_record('playerwords', (object)array_merge($defaults, $overrides));
        return $DB->get_record('playerwords', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Creates a course glossary with one approved entry.
     *
     * @param string $concept Entry concept text.
     * @param string $definition Entry definition text.
     * @return array{0: \stdClass, 1: \stdClass} [glossary, entry]
     */
    private function make_glossary_entry(string $concept, string $definition): array {
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $this->course->id]);
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, [
            'concept'    => $concept,
            'definition' => $definition,
            'approved'   => 1,
        ]);
        return [$glossary, $entry];
    }

    /**
     * A manually added word is trimmed, saved pre-approved, and attributed to its author.
     *
     * @covers \mod_playerwords\local\words_repository::add_manual_word
     * @return void
     */
    public function test_add_manual_word_inserts_approved_record(): void {
        global $DB;
        $instance = $this->make_instance();

        words_repository::add_manual_word($instance->id, $this->user->id, '  gato  ', '  felino domestico  ');

        $record = $DB->get_record('playerwords_words', ['playerwordsid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('gato', $record->word);
        $this->assertSame('gato', $record->concept);
        $this->assertSame('felino domestico', $record->hint);
        $this->assertSame('manual', $record->source);
        $this->assertEquals(1, $record->approved);
        $this->assertEquals($this->user->id, $record->addedby);
    }

    /**
     * word_exists() matches an existing word case-insensitively.
     *
     * @covers \mod_playerwords\local\words_repository::word_exists
     * @return void
     */
    public function test_word_exists_matches_case_insensitively(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'Planeta');

        $this->assertTrue(words_repository::word_exists($instance->id, 'planeta'));
        $this->assertTrue(words_repository::word_exists($instance->id, 'PLANETA'));
    }

    /**
     * word_exists() is false when no word matches, and scoped to the given instance.
     *
     * @covers \mod_playerwords\local\words_repository::word_exists
     * @return void
     */
    public function test_word_exists_false_when_no_match_or_wrong_instance(): void {
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $this->make_word($instancea->id, 'planeta');

        $this->assertFalse(words_repository::word_exists($instancea->id, 'estrela'));
        $this->assertFalse(words_repository::word_exists($instanceb->id, 'planeta'));
    }

    /**
     * word_exists() ignores a source: a word inserted as manual is matched by a check
     * that carries no source of its own, which is exactly what a duplicate-guard call
     * needs regardless of where the colliding text came from.
     *
     * @covers \mod_playerwords\local\words_repository::word_exists
     * @return void
     */
    public function test_word_exists_matches_regardless_of_source(): void {
        $instance = $this->make_instance();
        words_repository::add_ai_word($instance->id, $this->user->id, 'planeta', 'corpo celeste');

        $this->assertTrue(words_repository::word_exists($instance->id, 'planeta'));
    }

    /**
     * word_exists() ignores the excluded word id, so renaming a word to its own current
     * text is not reported as a collision with itself.
     *
     * @covers \mod_playerwords\local\words_repository::word_exists
     * @return void
     */
    public function test_word_exists_ignores_excluded_word_id(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'planeta');
        $wordid = (int)$DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instance->id], MUST_EXIST);

        $this->assertFalse(words_repository::word_exists($instance->id, 'planeta', $wordid));
    }

    /**
     * An AI-generated word is saved as pending approval, never pre-approved.
     *
     * @covers \mod_playerwords\local\words_repository::add_ai_word
     * @return void
     */
    public function test_add_ai_word_inserts_pending_unapproved(): void {
        global $DB;
        $instance = $this->make_instance();

        words_repository::add_ai_word($instance->id, $this->user->id, 'planeta', 'corpo celeste');

        $record = $DB->get_record('playerwords_words', ['playerwordsid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('ai', $record->source);
        $this->assertEquals(0, $record->approved);
    }

    /**
     * get_word_by_id() returns a word regardless of approval status, but only when
     * it belongs to the given activity instance.
     *
     * @covers \mod_playerwords\local\words_repository::get_word_by_id
     * @return void
     */
    public function test_get_word_by_id_ignores_approval_but_is_scoped_to_instance(): void {
        global $DB;
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $this->make_word($instancea->id, 'pendente', 0);
        $wordid = $DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instancea->id], MUST_EXIST);

        $found = words_repository::get_word_by_id((int)$wordid, $instancea->id);
        $this->assertNotNull($found);
        $this->assertSame('pendente', $found->word);

        $crosslookup = words_repository::get_word_by_id((int)$wordid, $instanceb->id);
        $this->assertNull($crosslookup);
    }

    /**
     * Updating a word trims the new text and stamps timemodified.
     *
     * @covers \mod_playerwords\local\words_repository::update_word
     * @return void
     */
    public function test_update_word_updates_fields_and_timemodified(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'antigo');
        $wordid = $DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instance->id], MUST_EXIST);
        $DB->set_field('playerwords_words', 'timemodified', 100, ['id' => $wordid]);

        $result = words_repository::update_word((int)$wordid, $instance->id, '  novo  ', '  dica nova  ');

        $this->assertTrue($result);
        $record = $DB->get_record('playerwords_words', ['id' => $wordid], '*', MUST_EXIST);
        $this->assertSame('novo', $record->word);
        $this->assertSame('novo', $record->concept);
        $this->assertSame('dica nova', $record->hint);
        $this->assertGreaterThan(100, $record->timemodified);
    }

    /**
     * A word cannot be updated through an instance id it does not belong to.
     *
     * @covers \mod_playerwords\local\words_repository::update_word
     * @return void
     */
    public function test_update_word_returns_false_for_wrong_instance(): void {
        global $DB;
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $this->make_word($instancea->id, 'palavra');
        $wordid = $DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instancea->id], MUST_EXIST);

        $result = words_repository::update_word((int)$wordid, $instanceb->id, 'outra', 'dica');

        $this->assertFalse($result);
        $record = $DB->get_record('playerwords_words', ['id' => $wordid], '*', MUST_EXIST);
        $this->assertSame('palavra', $record->word);
    }

    /**
     * A word is deleted when the given instance id actually owns it.
     *
     * @covers \mod_playerwords\local\words_repository::delete_word
     * @return void
     */
    public function test_delete_word_removes_when_instance_matches(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'apagar');
        $wordid = $DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instance->id], MUST_EXIST);

        $result = words_repository::delete_word((int)$wordid, $instance->id);

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('playerwords_words', ['id' => $wordid]));
    }

    /**
     * A word cannot be deleted through an instance id it does not belong to.
     *
     * @covers \mod_playerwords\local\words_repository::delete_word
     * @return void
     */
    public function test_delete_word_does_not_remove_when_instance_differs(): void {
        global $DB;
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $this->make_word($instancea->id, 'protegida');
        $wordid = $DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instancea->id], MUST_EXIST);

        $result = words_repository::delete_word((int)$wordid, $instanceb->id);

        $this->assertFalse($result);
        $this->assertTrue($DB->record_exists('playerwords_words', ['id' => $wordid]));
    }

    /**
     * Bulk delete removes only the specified ids, leaving the rest untouched.
     *
     * @covers \mod_playerwords\local\words_repository::delete_words_bulk
     * @return void
     */
    public function test_delete_words_bulk_removes_only_specified_ids(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'um');
        $this->make_word($instance->id, 'dois');
        $this->make_word($instance->id, 'tres');
        $ids = $DB->get_fieldset_select('playerwords_words', 'id', 'playerwordsid = :pid', ['pid' => $instance->id]);
        sort($ids);
        $todelete = array_slice($ids, 0, 2);
        $keep = array_slice($ids, 2);

        words_repository::delete_words_bulk($todelete, $instance->id);

        $remaining = $DB->get_fieldset_select('playerwords_words', 'id', 'playerwordsid = :pid', ['pid' => $instance->id]);
        $this->assertEquals($keep, array_values($remaining));
    }

    /**
     * Bulk delete with an empty id list is a no-op.
     *
     * @covers \mod_playerwords\local\words_repository::delete_words_bulk
     * @return void
     */
    public function test_delete_words_bulk_noop_on_empty_array(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'fica');

        words_repository::delete_words_bulk([], $instance->id);

        $this->assertEquals(1, $DB->count_records('playerwords_words', ['playerwordsid' => $instance->id]));
    }

    /**
     * Bulk approve marks every given word approved and stamps timemodified.
     *
     * @covers \mod_playerwords\local\words_repository::approve_words_bulk
     * @return void
     */
    public function test_approve_words_bulk_sets_approved_and_timemodified(): void {
        global $DB;
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'pendente1', 0);
        $this->make_word($instance->id, 'pendente2', 0);
        $ids = $DB->get_fieldset_select('playerwords_words', 'id', 'playerwordsid = :pid', ['pid' => $instance->id]);
        $DB->set_field_select('playerwords_words', 'timemodified', 0, 'playerwordsid = :pid', ['pid' => $instance->id]);

        words_repository::approve_words_bulk($ids, $instance->id);

        $records = $DB->get_records('playerwords_words', ['playerwordsid' => $instance->id]);
        foreach ($records as $record) {
            $this->assertEquals(1, $record->approved);
            $this->assertGreaterThan(0, $record->timemodified);
        }
    }

    /**
     * The word pool listing respects the requested sort column and limit.
     *
     * @covers \mod_playerwords\local\words_repository::get_recent_words
     * @return void
     */
    public function test_get_recent_words_orders_and_limits(): void {
        $instance = $this->make_instance();
        $this->make_word($instance->id, 'alfa');
        $this->make_word($instance->id, 'beta');
        $this->make_word($instance->id, 'gama');

        $all = words_repository::get_recent_words($instance->id, 0, 'word', 'ASC');
        $words = array_values(array_map(fn($record): string => $record->word, $all));
        $this->assertSame(['alfa', 'beta', 'gama'], $words);

        $limited = words_repository::get_recent_words($instance->id, 2, 'word', 'ASC');
        $this->assertCount(2, $limited);
    }

    /**
     * A word imported from a glossary reports that glossary's name in the listing.
     *
     * @covers \mod_playerwords\local\words_repository::get_recent_words
     * @return void
     */
    public function test_get_recent_words_includes_glossary_name(): void {
        global $DB;
        $instance = $this->make_instance();
        [$glossary] = $this->make_glossary_entry('termo', 'definicao');

        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'termo',
            'concept'       => 'termo',
            'hint'          => '',
            'source'        => 'glossary',
            'glossaryid'    => $glossary->id,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => 0,
        ]);

        $rows = words_repository::get_recent_words($instance->id);
        $row = reset($rows);
        $this->assertSame($glossary->name, $row->glossaryname);
    }

    /**
     * Sync is a no-op when the glossary source bit is not enabled.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_disabled_when_source_bit_not_set(): void {
        global $DB;
        [$glossary] = $this->make_glossary_entry('planeta', 'corpo celeste');
        $instance = $this->make_full_instance([
            'sources'    => PLAYERWORDS_SOURCE_MANUAL,
            'glossaryid' => $glossary->id,
        ]);

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(0, $imported);
        $this->assertSame(0, $DB->count_records('playerwords_words', ['playerwordsid' => $instance->id]));
    }

    /**
     * A single-word glossary concept is imported as one approved word.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_imports_single_word_concept(): void {
        global $DB;
        [$glossary] = $this->make_glossary_entry('planeta', 'corpo celeste que orbita uma estrela');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(1, $imported);
        $record = $DB->get_record('playerwords_words', ['playerwordsid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('planeta', $record->word);
        $this->assertSame('glossary', $record->source);
        $this->assertEquals(1, $record->approved);
        $this->assertSame('corpo celeste que orbita uma estrela', $record->hint);
    }

    /**
     * A multi-word concept is split into one candidate word per token when no
     * stopwords are configured.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_splits_multiword_concept_without_stopwords(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        [$glossary] = $this->make_glossary_entry('sistema solar', 'conjunto de planetas');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(2, $imported);
        $words = words_repository::get_recent_words($instance->id, 0, 'word', 'ASC');
        $wordtexts = array_values(array_map(fn($record): string => $record->word, $words));
        $this->assertSame(['sistema', 'solar'], $wordtexts);
    }

    /**
     * A configured stopword is dropped from a multi-word concept before import.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_filters_configured_stopwords(): void {
        global $DB;
        set_config('glossarystopwords', 'o', 'mod_playerwords');
        [$glossary] = $this->make_glossary_entry('o brasil', 'pais da america do sul');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(1, $imported);
        $record = $DB->get_record('playerwords_words', ['playerwordsid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('brasil', $record->word);
    }

    /**
     * Re-syncing after the glossary definition changed updates the existing word's
     * hint in place, instead of creating a duplicate.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_updates_hint_on_resync(): void {
        global $DB;
        [$glossary, $entry] = $this->make_glossary_entry('planeta', 'definicao original');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);

        words_repository::sync_glossary_words($instance);
        $DB->set_field('glossary_entries', 'definition', 'definicao atualizada', ['id' => $entry->id]);

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(0, $imported);
        $this->assertSame(1, $DB->count_records('playerwords_words', ['playerwordsid' => $instance->id]));
        $record = $DB->get_record('playerwords_words', ['playerwordsid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('definicao atualizada', $record->hint);
    }

    /**
     * When a previously imported glossary entry disappears, the next sync removes
     * the orphaned word, without touching manually added words.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_removes_orphaned_words(): void {
        global $DB;
        [$glossary, $entry] = $this->make_glossary_entry('planeta', 'corpo celeste');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);
        words_repository::add_manual_word($instance->id, $this->user->id, 'manual', 'palavra manual');

        words_repository::sync_glossary_words($instance);
        $this->assertSame(2, $DB->count_records('playerwords_words', ['playerwordsid' => $instance->id]));

        $DB->delete_records('glossary_entries', ['id' => $entry->id]);
        words_repository::sync_glossary_words($instance);

        $remaining = $DB->get_records('playerwords_words', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $remaining);
        $this->assertSame('manual', reset($remaining)->word);
    }

    /**
     * glossaryid = 0 imports from every glossary in the course, not just one.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_zero_glossaryid_covers_all_course_glossaries(): void {
        [$glossarya] = $this->make_glossary_entry('planeta', 'corpo celeste');
        [$glossaryb] = $this->make_glossary_entry('estrela', 'corpo luminoso');
        $instance = $this->make_full_instance(['glossaryid' => 0]);

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(2, $imported);
    }

    /**
     * A glossary concept whose text already belongs to a manually added word is skipped:
     * no duplicate row is inserted, and the manual word's own hint is left untouched.
     *
     * @covers \mod_playerwords\local\words_repository::sync_glossary_words
     * @return void
     */
    public function test_sync_glossary_words_skips_word_owned_by_another_source(): void {
        global $DB;
        [$glossary] = $this->make_glossary_entry('planeta', 'corpo celeste que orbita uma estrela');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);
        words_repository::add_manual_word($instance->id, $this->user->id, 'planeta', 'dica do professor');

        $imported = words_repository::sync_glossary_words($instance);

        $this->assertSame(0, $imported);
        $records = $DB->get_records('playerwords_words', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertSame('manual', $record->source);
        $this->assertSame('dica do professor', $record->hint);
    }

    /**
     * A multi-word glossary concept split into several sibling word rows is
     * reported by get_fragmented_concepts(), keyed by the shared concept text.
     *
     * @covers \mod_playerwords\local\words_repository::get_fragmented_concepts
     * @return void
     */
    public function test_get_fragmented_concepts_reports_split_multiword_concept(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        [$glossary] = $this->make_glossary_entry('sistema solar', 'conjunto de planetas');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);
        words_repository::sync_glossary_words($instance);

        $fragmented = words_repository::get_fragmented_concepts($instance->id);

        $this->assertSame(['sistema solar'], $fragmented);
    }

    /**
     * A single-word glossary concept never appears in get_fragmented_concepts(),
     * since it produced only one word row — nothing was actually split.
     *
     * @covers \mod_playerwords\local\words_repository::get_fragmented_concepts
     * @return void
     */
    public function test_get_fragmented_concepts_excludes_single_word_concept(): void {
        [$glossary] = $this->make_glossary_entry('planeta', 'corpo celeste que orbita uma estrela');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);
        words_repository::sync_glossary_words($instance);

        $this->assertSame([], words_repository::get_fragmented_concepts($instance->id));
    }

    /**
     * Manual and AI words always store concept = word (a single token, enforced at
     * insert time), so they can never collide into a false positive here even when
     * two of them happen to share the same text as their concept.
     *
     * @covers \mod_playerwords\local\words_repository::get_fragmented_concepts
     * @return void
     */
    public function test_get_fragmented_concepts_ignores_non_glossary_sources(): void {
        $instance = $this->make_full_instance();
        words_repository::add_manual_word($instance->id, $this->user->id, 'planeta', 'dica manual');
        words_repository::add_ai_word($instance->id, $this->user->id, 'estrela', 'dica da ia');

        $this->assertSame([], words_repository::get_fragmented_concepts($instance->id));
    }

    /**
     * The fragmented-concept check is scoped to its own activity instance — a
     * split concept in one instance must not leak into another instance's report,
     * even when both import from glossaries in the same course.
     *
     * @covers \mod_playerwords\local\words_repository::get_fragmented_concepts
     * @return void
     */
    public function test_get_fragmented_concepts_is_scoped_to_its_own_instance(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        [$glossary] = $this->make_glossary_entry('sistema solar', 'conjunto de planetas');
        $instance = $this->make_full_instance(['glossaryid' => $glossary->id]);
        $otherinstance = $this->make_full_instance(['glossaryid' => $glossary->id]);
        words_repository::sync_glossary_words($instance);

        $this->assertSame(['sistema solar'], words_repository::get_fragmented_concepts($instance->id));
        $this->assertSame([], words_repository::get_fragmented_concepts($otherinstance->id));
    }

    /**
     * A word with no issues never appears in get_inactive_words().
     *
     * @covers \mod_playerwords\local\words_repository::get_inactive_words
     * @return void
     */
    public function test_get_inactive_words_empty_when_no_issues(): void {
        $instance = $this->make_full_instance(['min_length' => 4, 'max_length' => 6]);
        $this->make_word($instance->id, 'boca');

        $this->assertSame([], words_repository::get_inactive_words($instance));
    }

    /**
     * An approved word outside the current length range is reported with reason
     * "length".
     *
     * @covers \mod_playerwords\local\words_repository::get_inactive_words
     * @return void
     */
    public function test_get_inactive_words_reports_length_mismatch(): void {
        $instance = $this->make_full_instance(['min_length' => 4, 'max_length' => 6]);
        $this->make_word($instance->id, 'planeta');

        $inactive = words_repository::get_inactive_words($instance);

        $this->assertCount(1, $inactive);
        $this->assertSame('planeta', $inactive[0]['word']);
        $this->assertSame('length', $inactive[0]['reason']);
    }

    /**
     * An approved word saved with a character the game cannot use is reported with
     * reason "invalidchars" — this can only happen from data saved before charset
     * validation existed on the manual-word form, or inserted through another path.
     *
     * @covers \mod_playerwords\local\words_repository::get_inactive_words
     * @return void
     */
    public function test_get_inactive_words_reports_invalid_charset(): void {
        $instance = $this->make_full_instance(['min_length' => 1, 'max_length' => 30]);
        $this->make_word($instance->id, 'café-com-leite');

        $inactive = words_repository::get_inactive_words($instance);

        $this->assertCount(1, $inactive);
        $this->assertSame('café-com-leite', $inactive[0]['word']);
        $this->assertSame('invalidchars', $inactive[0]['reason']);
    }

    /**
     * A pending (unapproved) word is never reported — it was never eligible to play
     * in the first place, so there is nothing "newly" inactive about it.
     *
     * @covers \mod_playerwords\local\words_repository::get_inactive_words
     * @return void
     */
    public function test_get_inactive_words_ignores_unapproved_words(): void {
        $instance = $this->make_full_instance(['min_length' => 4, 'max_length' => 6]);
        $this->make_word($instance->id, 'planeta', 0);

        $this->assertSame([], words_repository::get_inactive_words($instance));
    }

    /**
     * No attempts recorded means no entry in the draw-count map at all — a word
     * that was never drawn is absent, not present with a zero.
     *
     * @covers \mod_playerwords\local\words_repository::get_draw_counts
     * @return void
     */
    public function test_get_draw_counts_absent_when_never_drawn(): void {
        $instance = $this->make_full_instance();
        $this->make_word($instance->id, 'boca');

        $this->assertSame([], words_repository::get_draw_counts($instance->id));
    }

    /**
     * Every attempt row counts towards the total, regardless of outcome.
     *
     * @covers \mod_playerwords\local\words_repository::get_draw_counts
     * @return void
     */
    public function test_get_draw_counts_sums_all_attempts_regardless_of_outcome(): void {
        global $DB;

        $instance = $this->make_full_instance();
        $wordid = $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'boca',
            'concept'       => 'boca',
            'hint'          => '',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);

        foreach ([1, 0, 0] as $completed) {
            $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $instance->id,
                'userid'        => $this->user->id,
                'wordid'        => $wordid,
                'attempts_used' => 1,
                'time_used'     => 5,
                'completed'     => $completed,
                'score'         => $completed ? 100 : 0,
                'timecreated'   => time(),
                'timefinished'  => $completed ? time() : 0,
            ]);
        }

        $counts = words_repository::get_draw_counts($instance->id);

        $this->assertSame(3, $counts[$wordid]);
    }

    /**
     * Draw counts are scoped to their own instance — attempts recorded against the
     * same word id in a different instance must never be added in.
     *
     * @covers \mod_playerwords\local\words_repository::get_draw_counts
     * @return void
     */
    public function test_get_draw_counts_is_scoped_to_its_own_instance(): void {
        global $DB;

        $instance = $this->make_full_instance();
        $otherinstance = $this->make_full_instance();
        $wordid = $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $otherinstance->id,
            'word'          => 'boca',
            'concept'       => 'boca',
            'hint'          => '',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $otherinstance->id,
            'userid'        => $this->user->id,
            'wordid'        => $wordid,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $this->assertSame([], words_repository::get_draw_counts($instance->id));
        $this->assertSame(1, words_repository::get_draw_counts($otherinstance->id)[$wordid]);
    }

    /**
     * Counts only the candidate words within the requested length range, for a
     * specific glossary — mirrors what sync_glossary_words() would actually import.
     *
     * @covers \mod_playerwords\local\words_repository::count_glossary_candidates
     * @return void
     */
    public function test_count_glossary_candidates_counts_within_range(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        [$glossary] = $this->make_glossary_entry('sistema solar', 'conjunto de planetas');

        $count = words_repository::count_glossary_candidates($this->course->id, $glossary->id, 5, 6);

        // Sistema (7 letters) is out of range; solar (5 letters) is in range.
        $this->assertSame(1, $count);
    }

    /**
     * glossaryid = 0 counts candidates across every glossary in the course, not
     * just the first one created.
     *
     * @covers \mod_playerwords\local\words_repository::count_glossary_candidates
     * @return void
     */
    public function test_count_glossary_candidates_zero_covers_all_course_glossaries(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        $this->make_glossary_entry('planeta', 'corpo celeste');
        $secondglossary = $this->getDataGenerator()->create_module('glossary', ['course' => $this->course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($secondglossary, [
            'concept'    => 'estrela',
            'definition' => 'corpo celeste luminoso',
            'approved'   => 1,
        ]);

        $count = words_repository::count_glossary_candidates($this->course->id, 0, 1, 30);

        $this->assertSame(2, $count);
    }

    /**
     * Two different concepts tokenising into the same word are only counted once
     * — the same deduplication sync_glossary_words() itself applies.
     *
     * @covers \mod_playerwords\local\words_repository::count_glossary_candidates
     * @return void
     */
    public function test_count_glossary_candidates_deduplicates_repeated_tokens(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $generator->create_content($glossary, ['concept' => 'planeta', 'definition' => 'um', 'approved' => 1]);
        $generator->create_content($glossary, ['concept' => 'Planeta', 'definition' => 'dois', 'approved' => 1]);

        $count = words_repository::count_glossary_candidates($this->course->id, $glossary->id, 1, 30);

        $this->assertSame(1, $count);
    }

    /**
     * A glossary id that belongs to a different course is never counted, even
     * though the id itself is a real, valid glossary — the instance-isolation
     * rule for any externally-supplied id.
     *
     * @covers \mod_playerwords\local\words_repository::count_glossary_candidates
     * @return void
     */
    public function test_count_glossary_candidates_ignores_glossary_from_another_course(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $foreignglossary = $this->getDataGenerator()->create_module('glossary', ['course' => $othercourse->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($foreignglossary, [
            'concept'    => 'planeta',
            'definition' => 'corpo celeste',
            'approved'   => 1,
        ]);

        $count = words_repository::count_glossary_candidates($this->course->id, $foreignglossary->id, 1, 30);

        $this->assertSame(0, $count);
    }

    /**
     * A course with no glossaries at all counts zero, without error.
     *
     * @covers \mod_playerwords\local\words_repository::count_glossary_candidates
     * @return void
     */
    public function test_count_glossary_candidates_zero_when_no_glossaries_exist(): void {
        $emptycourse = $this->getDataGenerator()->create_course();

        $this->assertSame(0, words_repository::count_glossary_candidates($emptycourse->id, 0, 1, 30));
    }
}
