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
 * Seed script for manual testing of mod_playerwords (PT-BR).
 *
 * Creates a demo course in Brazilian Portuguese with a teacher, students, a glossary, a
 * block_playerhud instance with items, and four PlayerWords activities that exercise every
 * combination of word source (manual, glossary, AI-pending) and PlayerHUD integration
 * (round cost, hint cost, win grant, and the infinite-round anti-farming rule). Run with
 * --reset to wipe and recreate the demo course from scratch.
 *
 * Usage:
 *   php mod/playerwords/cli/seed_pt_br.php --password=SuaSenhaDev
 *   php mod/playerwords/cli/seed_pt_br.php --password=SuaSenhaDev --reset
 *   php mod/playerwords/cli/seed_pt_br.php --password=SuaSenhaDev --force
 *
 * O parâmetro --password é obrigatório e define a senha de login de todos os usuários demo
 * criados por este script (ex.: seed_teacher, seed_alice, …). Use-a para entrar no Moodle
 * como um desses usuários de teste. O script recusa execução em sites que não sejam de
 * desenvolvimento, a menos que --force seja informado (use em domínios de dev personalizados).
 *
 * Os usuários seed (seed_teacher, seed_alice, seed_bob, seed_carol, seed_dave, seed_eve) usam
 * exatamente os mesmos usernames do seed de block_playerhud (blocks/playerhud/cli/seed_pt_br.php)
 * de propósito: rodar os dois seeds no mesmo site faz o mesmo "jogador" acumular XP e itens do
 * PlayerHUD tanto pelo curso demo do PlayerHUD quanto pelo curso demo do PlayerWords, o que é
 * exatamente o cenário de integração que este script existe para demonstrar. --reset aqui só
 * remove o curso demo do PlayerWords (e tudo dentro dele: atividades, glossário, instância do
 * bloco); os usuários seed nunca são apagados por este script, pois podem estar em uso pelo
 * curso demo do PlayerHUD.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/mod/playerwords/lib.php');

use mod_playerwords\local\gameplay_service;
use mod_playerwords\local\hud_service;
use mod_playerwords\local\words_repository;

// Suppress email sending in dev/test environments (no sendmail in Docker).
$CFG->noemailever = true;

[$options, $unrecognised] = cli_get_params(
    ['reset' => false, 'help' => false, 'password' => '', 'force' => false],
    ['h' => 'help', 'r' => 'reset', 'p' => 'password', 'f' => 'force']
);

if ($options['help']) {
    cli_writeln("Seed script for mod_playerwords manual testing.\n");
    cli_writeln("Options:");
    cli_writeln("  --password=<pass>  Required. Password for all seed users.");
    cli_writeln("  --reset            Wipe the demo course and recreate everything from scratch.");
    cli_writeln("  --force            Skip the development-site guard (use on custom dev domains).");
    cli_writeln("  --help             Show this message.");
    exit(0);
}

// Refuse to run without an explicit password to avoid known-credential accounts in production.
if (empty($options['password'])) {
    cli_error("ERROR: --password=<pass> is required. Aborting to prevent known-credential accounts.");
}

// Guard: refuse to run if this looks like a production environment.
// Use --force to bypass when running on a custom development domain.
if (
    !$options['force'] && !empty($CFG->wwwroot)
    && !preg_match('/localhost|127\.0\.0\.1|\.local(:|\/|$)|\.test(:|\/|$)/i', $CFG->wwwroot)
) {
    cli_error(
        "ERROR: This script must not be run on a non-development site ({$CFG->wwwroot}).\n" .
        "If this is intentional, re-run with --force."
    );
}

if (!class_exists(\block_playerhud\game::class)) {
    cli_error("ERROR: block_playerhud must be installed to demonstrate its integration with mod_playerwords.");
}

/** @var string Shortname of the demo course created by this seed script. */
const SEED_COURSE_SHORTNAME = 'playerwords-demo-ptbr';

/** @var string Password for all seed users, supplied via --password flag. */
define('SEED_PASSWORD', $options['password']);

cli_writeln("=== mod_playerwords seed ===\n");

// 1. Reset.
if ($options['reset']) {
    $existing = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
    if ($existing) {
        cli_writeln("Removendo curso demo existente (id={$existing->id})...");
        delete_course($existing, false);
        cli_writeln("Curso removido.\n");
    }
}

// 2. Course.
$course = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
if ($course) {
    cli_writeln("Curso demo já existe (id={$course->id}). Use --reset para recriar.\n");
} else {
    $coursedata = (object) [
        'fullname'    => 'PlayerWords Demo (PT-BR)',
        'shortname'   => SEED_COURSE_SHORTNAME,
        'summary'     => 'Curso criado automaticamente pelo seed do PlayerWords para testes manuais.',
        'format'      => 'topics',
        'numsections' => 2,
        'visible'     => 1,
        'category'    => 1,
    ];
    $course = create_course($coursedata);
    cli_writeln("Curso criado: id={$course->id}");
}

$coursecontext = context_course::instance($course->id);

// 3. Users.

/**
 * Creates a user if it does not already exist.
 *
 * Usernames intentionally match blocks/playerhud/cli/seed_pt_br.php so the same seed
 * "player" can be reused across both plugins' demo courses.
 *
 * @param string $username Username.
 * @param string $firstname First name.
 * @param string $lastname Last name.
 * @param string $password Plaintext password.
 * @return stdClass User record.
 */
function seed_create_user(string $username, string $firstname, string $lastname, string $password): stdClass {
    global $DB, $CFG;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        return $existing;
    }

    $user = (object) [
        'auth'         => 'manual',
        'confirmed'    => 1,
        'policyagreed' => 1,
        'deleted'      => 0,
        'mnethostid'   => $CFG->mnet_localhost_id,
        'username'     => $username,
        'password'     => hash_internal_user_password($password),
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $username . '@playerhud.test',
        'lang'         => 'pt_br',
        'timezone'     => '99',
        'timecreated'  => time(),
        'timemodified' => time(),
    ];

    $user->id = $DB->insert_record('user', $user);
    return $user;
}

$teacher = seed_create_user('seed_teacher', 'Mestre', 'Dungeon', SEED_PASSWORD);
$students = [];
$studentnames = [
    ['seed_alice', 'Alice', 'Espada'],
    ['seed_bob', 'Bob', 'Arco'],
    ['seed_carol', 'Carol', 'Cajado'],
    ['seed_dave', 'Dave', 'Escudo'],
    ['seed_eve', 'Eve', 'Adaga'],
];
foreach ($studentnames as [$uname, $fname, $lname]) {
    $students[] = seed_create_user($uname, $fname, $lname, SEED_PASSWORD);
}
cli_writeln("Usuários criados/encontrados: 1 professor + " . count($students) . " alunos.");

// 4. Enrolment.
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$instance) {
    $enrolid = $enrol->add_default_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $enrolid]);
}

$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

$enrol->enrol_user($instance, $teacher->id, $teacherrole->id);
foreach ($students as $student) {
    $enrol->enrol_user($instance, $student->id, $studentrole->id);
}
cli_writeln("Matrículas concluídas.");

$DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
$course->enablecompletion = 1;

// The add_moduleinfo API requires a logged-in user context.
$origuser = $USER;
\core\session\manager::set_user(get_admin());

// 5. Glossary (word source demo).

/**
 * Creates a course module if one with the same name does not already exist.
 *
 * @param stdClass $course Course record.
 * @param string $modulename Module type (e.g., 'glossary', 'playerwords').
 * @param array $extra Fields merged into the moduleinfo object.
 * @return stdClass|null Course module record, or null if unavailable.
 */
function seed_create_module(stdClass $course, string $modulename, array $extra): ?stdClass {
    global $DB;

    $moduleid = $DB->get_field('modules', 'id', ['name' => $modulename]);
    if (!$moduleid) {
        return null;
    }

    $existingcm = $DB->get_record_sql(
        "SELECT cm.* FROM {course_modules} cm
           JOIN {{$modulename}} m ON m.id = cm.instance
          WHERE cm.course = :course AND m.name = :name",
        ['course' => $course->id, 'name' => $extra['name']]
    );
    if ($existingcm) {
        return get_coursemodule_from_id($modulename, $existingcm->id);
    }

    $moduleinfo = (object) array_merge([
        'modulename'         => $modulename,
        'module'             => $moduleid,
        'course'             => $course->id,
        'section'            => 1,
        'visible'            => 1,
        'intro'              => '',
        'introformat'        => FORMAT_HTML,
        'cmidnumber'         => '',
        'completion'         => COMPLETION_TRACKING_NONE,
        'completionview'     => 0,
        'completionexpected' => 0,
    ], $extra);

    $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
    return get_coursemodule_from_id($modulename, $moduleinfo->coursemodule);
}

/**
 * Inserts an approved glossary entry directly, bypassing the glossary editing form.
 *
 * @param int $glossaryid Glossary instance id.
 * @param int $userid Author user id.
 * @param string $concept Single word the entry is about.
 * @param string $definition Definition text shown as the PlayerWords hint.
 * @return void
 */
function seed_add_glossary_entry(int $glossaryid, int $userid, string $concept, string $definition): void {
    global $DB;

    if ($DB->record_exists('glossary_entries', ['glossaryid' => $glossaryid, 'concept' => $concept])) {
        return;
    }

    $DB->insert_record('glossary_entries', (object) [
        'glossaryid'       => $glossaryid,
        'userid'           => $userid,
        'concept'          => $concept,
        'definition'       => $definition,
        'definitionformat' => FORMAT_HTML,
        'attachment'       => '',
        'timecreated'      => time(),
        'timemodified'     => time(),
        'approved'         => 1,
    ]);
}

$cmglossary = seed_create_module($course, 'glossary', [
    'name'          => 'Vocabulário do Curso',
    'intro'         => 'Termos que alimentam o modo Glossário do PlayerWords.',
    'section'       => 1,
    'displayformat' => 'dictionary',
    'assessed'      => 0,
]);

if ($cmglossary) {
    $glossaryid = (int) $cmglossary->instance;
    seed_add_glossary_entry($glossaryid, $teacher->id, 'Casa', 'Construção onde uma família mora.');
    seed_add_glossary_entry($glossaryid, $teacher->id, 'Mundo', 'O planeta Terra e tudo que existe nele.');
    seed_add_glossary_entry($glossaryid, $teacher->id, 'Vida', 'Estado de quem está vivo, capaz de crescer e sentir.');
    seed_add_glossary_entry($glossaryid, $teacher->id, 'Tempo', 'Sucessão de momentos: passado, presente e futuro.');
    seed_add_glossary_entry($glossaryid, $teacher->id, 'Flor', 'Parte colorida e perfumada de uma planta.');
    seed_add_glossary_entry($glossaryid, $teacher->id, 'Janela', 'Abertura na parede por onde entra luz e ar.');
    cli_writeln("Glossário criado: id={$glossaryid}, 6 termos aprovados.");
} else {
    cli_writeln("Módulo 'glossary' indisponível — pulei a criação do glossário.");
}

// 6. PlayerHUD block instance + items.
$blockinstance = $DB->get_record('block_instances', [
    'blockname' => 'playerhud',
    'parentcontextid' => $coursecontext->id,
]);

if (!$blockinstance) {
    $config = (object) [
        'xp_per_level'   => 100,
        'max_levels'     => 10,
        'enable_rpg'     => 0,
        'enable_ranking' => 1,
        'enable_items'   => 1,
        'enable_quests'  => 0,
        'help_content'   => ['text' => '', 'format' => FORMAT_HTML],
    ];

    $bi = (object) [
        'blockname'         => 'playerhud',
        'parentcontextid'   => $coursecontext->id,
        'showinsubcontexts' => 0,
        'pagetypepattern'   => 'course-view-*',
        'subpagepattern'    => null,
        'defaultregion'     => 'side-pre',
        'defaultweight'     => 0,
        'configdata'        => base64_encode(serialize($config)),
        'timecreated'       => time(),
        'timemodified'      => time(),
    ];

    $bi->id = $DB->insert_record('block_instances', $bi);
    context_block::instance($bi->id);
    $blockinstance = $bi;
    cli_writeln("Bloco PlayerHUD criado: id={$bi->id}");
} else {
    cli_writeln("Bloco PlayerHUD já existe: id={$blockinstance->id}");
}

$blockinstanceid = (int) $blockinstance->id;

/**
 * Returns existing PlayerHUD item or creates a new one, scoped to this demo's own block
 * instance (never shared with blocks/playerhud/cli/seed_pt_br.php's own item set).
 *
 * @param int $blockinstanceid Block instance id.
 * @param string $name Item name.
 * @param int $xp XP value awarded per unit granted (subject to suppressxp on the caller side).
 * @param string $description Item description.
 * @param string $image Emoji used as the item image.
 * @return stdClass Item record.
 */
function seed_upsert_hud_item(int $blockinstanceid, string $name, int $xp, string $description, string $image): stdClass {
    global $DB;

    $existing = $DB->get_record('block_playerhud_items', ['blockinstanceid' => $blockinstanceid, 'name' => $name]);
    if ($existing) {
        return $existing;
    }

    $now = time();
    $record = (object) [
        'blockinstanceid'   => $blockinstanceid,
        'name'              => $name,
        'description'       => $description,
        'image'             => $image,
        'xp'                => $xp,
        'enabled'           => 1,
        'required_class_id' => '0',
        'secret'            => 0,
        'tradable'          => 0,
        'timecreated'       => $now,
        'timemodified'      => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_items', $record);
    return $record;
}

$itemkey = seed_upsert_hud_item($blockinstanceid, 'Chave da Palavra', 5, 'Necessária para iniciar uma rodada.', '🔑');
$itemhint = seed_upsert_hud_item($blockinstanceid, 'Pista Extra', 3, 'Revela a dica escondida da palavra.', '💡');
$itemmedal = seed_upsert_hud_item($blockinstanceid, 'Medalha das Palavras', 40, 'Concedida a quem vence uma rodada.', '🏅');
cli_writeln("Itens do PlayerHUD prontos: Chave da Palavra, Pista Extra, Medalha das Palavras.");

// Starter stock so students can actually afford round/hint costs below. Seed currency, so XP
// is suppressed — a student should not earn founding XP just from being handed a starting kit.
foreach ($students as $student) {
    hud_service::grant_items($blockinstanceid, $student->id, $itemkey->id, 8, true);
    hud_service::grant_items($blockinstanceid, $student->id, $itemhint->id, 5, true);
}
cli_writeln("Estoque inicial de Chave da Palavra e Pista Extra concedido a todos os alunos.");

// 7. PlayerWords activities.

/**
 * Finishes one simulated round: computes score/ranking points with the real formulas and
 * inserts the attempt row, spread into the past so ranking/history views show real variety.
 *
 * @param stdClass $instance Activity instance record (needs grade, *scoringmode, max_attempts).
 * @param int $userid Student user id.
 * @param int $wordid Word id played.
 * @param int $attemptsused Attempts used this round.
 * @param bool $completed Whether the round was won.
 * @param int $timeused Seconds spent on the round.
 * @param int $daysago How many days in the past this round finished.
 * @return void
 */
function seed_finish_round(
    stdClass $instance,
    int $userid,
    int $wordid,
    int $attemptsused,
    bool $completed,
    int $timeused,
    int $daysago
): void {
    global $DB;

    $score = gameplay_service::calculate_round_score($instance, $attemptsused, $timeused, $completed);
    $rankingpoints = gameplay_service::calculate_ranking_points($instance, $attemptsused, $completed);
    $finishedat = time() - ($daysago * DAYSECS);

    $DB->insert_record('playerwords_attempts', (object) [
        'playerwordsid' => $instance->id,
        'userid'        => $userid,
        'wordid'        => $wordid,
        'attempts_used' => $attemptsused,
        'time_used'     => $timeused,
        'completed'     => $completed ? 1 : 0,
        'score'         => $score,
        'rankingpoints' => $rankingpoints,
        'timecreated'   => $finishedat - $timeused,
        'timefinished'  => $finishedat,
    ]);
}

/**
 * Picks a random approved word id for the given activity instance.
 *
 * @param int $instanceid Activity instance id.
 * @return int
 */
function seed_random_word_id(int $instanceid): int {
    global $DB;

    $words = array_values($DB->get_records('playerwords_words', [
        'playerwordsid' => $instanceid,
        'approved'      => 1,
    ], 'id ASC', 'id'));

    return $words[array_rand($words)]->id;
}

// 7a. "Desafio Rápido" — manual words only, no PlayerHUD costs, unlimited rounds.
$cmquick = seed_create_module($course, 'playerwords', [
    'name'                => 'Desafio Rápido',
    'intro'               => 'Jogue quantas rodadas quiser, sem custo de itens.',
    'section'             => 1,
    'source_manual'       => 1,
    'source_glossary'     => 0,
    'glossaryid'          => 0,
    'wordmode'            => PLAYERWORDS_WORDMODE_RANDOM,
    'max_attempts'        => 6,
    'min_length'          => 4,
    'max_length'          => 6,
    'timer_minutes'       => 0,
    'show_ranking'        => 1,
    'rankingscoringmode'  => PLAYERWORDS_SCORING_BINARY,
    'max_rounds'          => 0,
    'cooldown_amount'     => 0,
    'cooldown_unit'       => 'minutes',
    'grade'               => 100,
    'grademethod'         => PLAYERWORDS_GRADE_HIGHEST,
    'gradescoringmode'    => PLAYERWORDS_SCORING_BINARY,
]);

if ($cmquick) {
    $instancequick = $DB->get_record('playerwords', ['id' => $cmquick->instance], '*', MUST_EXIST);
    $quickwords = [
        ['Gato', 'Animal doméstico que mia.'],
        ['Livro', 'Você o lê para aprender.'],
        ['Ponte', 'Liga duas margens de um rio.'],
        ['Escola', 'Lugar onde você estuda.'],
        ['Maçã', 'Fruta vermelha ou verde, rica em fibras.'],
        ['Festa', 'Comemoração com música e bolo.'],
        ['Cidade', 'Onde moram muitas pessoas.'],
        ['Nuvem', 'Fica no céu e pode trazer chuva.'],
    ];
    foreach ($quickwords as [$word, $hint]) {
        words_repository::add_manual_word($instancequick->id, $teacher->id, $word, $hint);
    }
    cli_writeln("Atividade 'Desafio Rápido' criada: " . count($quickwords) . " palavras manuais.");

    // Alice and Bob play several unlimited rounds with no cooldown; Carol only tried once.
    $daysago = 6;
    foreach ([$students[0], $students[1]] as $student) {
        for ($round = 0; $round < 4; $round++) {
            $wordid = seed_random_word_id($instancequick->id);
            $completed = $round !== 1;
            $attempts = $completed ? random_int(1, 4) : (int) $instancequick->max_attempts;
            seed_finish_round($instancequick, $student->id, $wordid, $attempts, $completed, random_int(20, 90), $daysago);
            $daysago--;
        }
    }
    seed_finish_round($instancequick, $students[2]->id, seed_random_word_id($instancequick->id), 3, true, 45, 2);
    playerwords_update_grades($instancequick);
    cli_writeln("Rodadas simuladas em 'Desafio Rápido' para Alice, Bob e Carol.");
} else {
    cli_writeln("Módulo 'playerwords' indisponível — pulei 'Desafio Rápido'.");
}

// 7b. "Vocabulário do Glossário" — glossary-sourced words, shared word mode, linear scoring.
$cmglossarygame = seed_create_module($course, 'playerwords', [
    'name'                => 'Vocabulário do Glossário',
    'intro'               => 'As palavras vêm do glossário do curso; todos jogam a mesma sequência.',
    'section'             => 1,
    'source_manual'       => 0,
    'source_glossary'     => 1,
    'glossaryid'          => 0,
    'wordmode'            => PLAYERWORDS_WORDMODE_SHARED,
    'max_attempts'        => 5,
    'min_length'          => 4,
    'max_length'          => 6,
    'timer_minutes'       => 0,
    'show_ranking'        => 1,
    'rankingscoringmode'  => PLAYERWORDS_SCORING_LINEAR,
    'max_rounds'          => 5,
    'cooldown_amount'     => 0,
    'cooldown_unit'       => 'minutes',
    'grade'               => 100,
    'grademethod'         => PLAYERWORDS_GRADE_AVERAGE_ALL,
    'gradescoringmode'    => PLAYERWORDS_SCORING_LINEAR,
]);

if ($cmglossarygame) {
    // The add_moduleinfo() call above already imported the glossary words, via
    // playerwords_add_instance()'s own call to sync_glossary_words() — no need to repeat it.
    $instanceglossary = $DB->get_record('playerwords', ['id' => $cmglossarygame->instance], '*', MUST_EXIST);
    $wordcount = $DB->count_records('playerwords_words', ['playerwordsid' => $instanceglossary->id, 'approved' => 1]);
    cli_writeln("Atividade 'Vocabulário do Glossário' criada: {$wordcount} palavras importadas do glossário.");

    $daysago = 5;
    foreach ($students as $idx => $student) {
        $rounds = 2 + ($idx % 3);
        for ($round = 0; $round < $rounds; $round++) {
            $wordid = seed_random_word_id($instanceglossary->id);
            $completed = ($round % 3) !== 2;
            $attempts = $completed ? random_int(1, 4) : (int) $instanceglossary->max_attempts;
            seed_finish_round($instanceglossary, $student->id, $wordid, $attempts, $completed, random_int(15, 60), $daysago);
            $daysago--;
        }
    }
    playerwords_update_grades($instanceglossary);
    cli_writeln("Rodadas simuladas em 'Vocabulário do Glossário' para todos os alunos.");
} else {
    cli_writeln("Módulo 'playerwords' indisponível — pulei 'Vocabulário do Glossário'.");
}

// 7c. "Missão com PlayerHUD" — manual + AI-pending words, HUD round/hint cost, win grant,
// limited rounds (so wins grant full item XP — no anti-farming suppression here).
$cmhud = seed_create_module($course, 'playerwords', [
    'name'                       => 'Missão com PlayerHUD',
    'intro'                      => 'Gaste Chave da Palavra para jogar, Pista Extra para dicas e ganhe medalhas.',
    'section'                    => 2,
    'source_manual'              => 1,
    'source_glossary'            => 0,
    'glossaryid'                 => 0,
    'wordmode'                   => PLAYERWORDS_WORDMODE_RANDOM,
    'max_attempts'               => 6,
    'min_length'                 => 4,
    'max_length'                 => 6,
    'timer_minutes'              => 0,
    'show_ranking'               => 1,
    'rankingscoringmode'         => PLAYERWORDS_SCORING_BINARY,
    'max_rounds'                 => 4,
    'cooldown_amount'            => 1,
    'cooldown_unit'              => 'hours',
    'hud_round_cost_item'        => $itemkey->id,
    'hud_round_cost_qty'         => 1,
    'hud_hint_cost_item'         => $itemhint->id,
    'hud_hint_cost_qty'          => 1,
    'hud_win_grant_item'         => $itemmedal->id,
    'hud_win_grant_qty'          => 1,
    'grade'                      => 100,
    'grademethod'                => PLAYERWORDS_GRADE_FIRST,
    'gradescoringmode'           => PLAYERWORDS_SCORING_BINARY,
    'completion'                 => COMPLETION_TRACKING_AUTOMATIC,
    'completionattemptsenabled'  => 1,
    'completionattempts'         => 3,
]);

if ($cmhud) {
    $instancehud = $DB->get_record('playerwords', ['id' => $cmhud->instance], '*', MUST_EXIST);
    $hudwords = [
        ['Anel', 'Joia usada no dedo.'],
        ['Torre', 'Construção alta e estreita.'],
        ['Chave', 'Usada para abrir portas.'],
        ['Espada', 'Arma de lâmina longa.'],
        ['Dragão', 'Criatura lendária que cospe fogo.'],
        ['Poder', 'Capacidade de realizar algo.'],
    ];
    foreach ($hudwords as [$word, $hint]) {
        words_repository::add_manual_word($instancehud->id, $teacher->id, $word, $hint);
    }
    // AI-pending queue demo: one still awaiting teacher approval, one already approved.
    words_repository::add_ai_word($instancehud->id, $teacher->id, 'Enigma', 'Charada difícil de resolver.');
    words_repository::add_ai_word($instancehud->id, $teacher->id, 'Magia', 'Força sobrenatural.');
    $aiapproveid = $DB->get_field('playerwords_words', 'id', ['playerwordsid' => $instancehud->id, 'word' => 'Magia']);
    if ($aiapproveid) {
        words_repository::approve_words_bulk([(int) $aiapproveid], $instancehud->id);
    }
    cli_writeln(
        "Atividade 'Missão com PlayerHUD' criada: " . count($hudwords)
        . " palavras manuais + 2 sugestões de IA (1 pendente, 1 aprovada)."
    );

    $daysago = 4;
    foreach ($students as $idx => $student) {
        $rounds = min((int) $instancehud->max_rounds, 2 + ($idx % 3));
        for ($round = 0; $round < $rounds; $round++) {
            hud_service::consume_items($blockinstanceid, $student->id, $itemkey->id, 1);
            if ($round % 2 === 0) {
                hud_service::consume_items($blockinstanceid, $student->id, $itemhint->id, 1);
            }

            $wordid = seed_random_word_id($instancehud->id);
            $completed = $round !== 1;
            $attempts = $completed ? random_int(1, 4) : (int) $instancehud->max_attempts;
            seed_finish_round($instancehud, $student->id, $wordid, $attempts, $completed, random_int(30, 120), $daysago);

            // Win grant only fires on a genuine round win, exactly like round_service::submit_guess().
            if ($completed) {
                hud_service::grant_items($blockinstanceid, $student->id, $itemmedal->id, 1, false);
            }
            $daysago--;
        }
    }
    playerwords_update_grades($instancehud);

    $modinfo = get_fast_modinfo($course);
    $cminfo = $modinfo->get_cm($cmhud->id);
    $completioninfo = new completion_info($course);
    foreach ($students as $student) {
        $completioninfo->update_state($cminfo, COMPLETION_COMPLETE, $student->id);
    }
    cli_writeln("Rodadas simuladas em 'Missão com PlayerHUD' — economia do PlayerHUD debitada/creditada de verdade.");
} else {
    cli_writeln("Módulo 'playerwords' indisponível — pulei 'Missão com PlayerHUD'.");
}

// 7d. "Modo Infinito PlayerHUD" — unlimited rounds with a win grant configured, demonstrating
// the anti-farming rule: the medal is still awarded, but its XP is withheld (suppressxp=true),
// exactly as round_service::finish_round() does for any activity where max_rounds is 0.
$cminfinite = seed_create_module($course, 'playerwords', [
    'name'                => 'Modo Infinito PlayerHUD',
    'intro'               => 'Jogue sem limite de rodadas — as medalhas continuam, mas sem XP extra.',
    'section'             => 2,
    'source_manual'       => 1,
    'source_glossary'     => 0,
    'glossaryid'          => 0,
    'wordmode'            => PLAYERWORDS_WORDMODE_RANDOM,
    'max_attempts'        => 6,
    'min_length'          => 4,
    'max_length'          => 6,
    'timer_minutes'       => 0,
    'show_ranking'        => 1,
    'rankingscoringmode'  => PLAYERWORDS_SCORING_BINARY,
    'max_rounds'          => 0,
    'cooldown_amount'     => 0,
    'cooldown_unit'       => 'minutes',
    'hud_win_grant_item'  => $itemmedal->id,
    'hud_win_grant_qty'   => 1,
    'grade'               => 100,
    'grademethod'         => PLAYERWORDS_GRADE_AVERAGE,
    'gradescoringmode'    => PLAYERWORDS_SCORING_BINARY,
]);

if ($cminfinite) {
    $instanceinfinite = $DB->get_record('playerwords', ['id' => $cminfinite->instance], '*', MUST_EXIST);
    $infinitewords = [
        ['Sorte', 'Acaso favorável.'],
        ['Teste', 'Verificação de algo.'],
        ['Ponto', 'Marca ou lugar específico.'],
        ['Campo', 'Área aberta de terra.'],
        ['Lago', 'Corpo de água cercado por terra.'],
    ];
    foreach ($infinitewords as [$word, $hint]) {
        words_repository::add_manual_word($instanceinfinite->id, $teacher->id, $word, $hint);
    }
    cli_writeln("Atividade 'Modo Infinito PlayerHUD' criada: " . count($infinitewords) . " palavras manuais.");

    $daysago = 3;
    foreach ($students as $idx => $student) {
        $rounds = 2 + ($idx % 2);
        for ($round = 0; $round < $rounds; $round++) {
            $wordid = seed_random_word_id($instanceinfinite->id);
            $completed = $round !== 1;
            $attempts = $completed ? random_int(1, 4) : (int) $instanceinfinite->max_attempts;
            seed_finish_round($instanceinfinite, $student->id, $wordid, $attempts, $completed, random_int(20, 80), $daysago);
            if ($completed) {
                // Suppressxp = true: same rule mod_playerwords itself applies for max_rounds = 0.
                hud_service::grant_items($blockinstanceid, $student->id, $itemmedal->id, 1, true);
            }
        }
        $daysago--;
    }
    playerwords_update_grades($instanceinfinite);
    cli_writeln("Rodadas simuladas em 'Modo Infinito PlayerHUD' — medalhas sem XP (regra antifarming).");
} else {
    cli_writeln("Módulo 'playerwords' indisponível — pulei 'Modo Infinito PlayerHUD'.");
}

\core\session\manager::set_user($origuser);

// 8. Summary.
$wwwroot = $CFG->wwwroot;
$courseurl = "{$wwwroot}/course/view.php?id={$course->id}";

cli_writeln("\n" . str_repeat('=', 60));
cli_writeln("SEED CONCLUÍDO");
cli_writeln(str_repeat('=', 60));
cli_writeln("Curso:           {$courseurl}");
cli_writeln("Bloco PlayerHUD: id={$blockinstanceid}");
cli_writeln("");
cli_writeln("USUÁRIOS (senha: " . SEED_PASSWORD . ")");
cli_writeln(str_pad("Username", 20) . str_pad("Nome", 20) . "Papel");
cli_writeln(str_repeat('-', 55));
cli_writeln(str_pad($teacher->username, 20) . str_pad($teacher->firstname . ' ' . $teacher->lastname, 20) . "Professor");
foreach ($students as $s) {
    $keys = hud_service::get_available_quantity($blockinstanceid, $s->id, $itemkey->id);
    $hints = hud_service::get_available_quantity($blockinstanceid, $s->id, $itemhint->id);
    $medals = hud_service::get_available_quantity($blockinstanceid, $s->id, $itemmedal->id);
    $role = "Aluno (chaves={$keys}, pistas={$hints}, medalhas={$medals})";
    cli_writeln(str_pad($s->username, 20) . str_pad($s->firstname . ' ' . $s->lastname, 20) . $role);
}
cli_writeln(str_repeat('=', 60));
cli_writeln("Atividades: Desafio Rápido, Vocabulário do Glossário, Missão com PlayerHUD, Modo Infinito PlayerHUD.");
cli_writeln("Para recriar tudo do zero: php mod/playerwords/cli/seed_pt_br.php --password=" . SEED_PASSWORD . " --reset");
