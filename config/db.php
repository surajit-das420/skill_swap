<?php
declare(strict_types=1);

/**
 * Database connection & automatic SQLite schema + migration + seed initialization.
 */
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }

    $dbPath = $dataDir . DIRECTORY_SEPARATOR . 'skillswap.db';
    $isNewDb = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    initializeSchema($pdo);
    if ($isNewDb || (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        seedDatabase($pdo);
    }
    ensurePostSeedDefaults($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            initials TEXT NOT NULL,
            avatar_color TEXT NOT NULL DEFAULT '#503CE8',
            availability TEXT NOT NULL DEFAULT 'Weekends, online',
            location TEXT NOT NULL DEFAULT 'Online',
            bio TEXT NOT NULL DEFAULT '',
            role TEXT NOT NULL DEFAULT 'user',
            status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS user_skills (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            skill_name TEXT NOT NULL,
            skill_type TEXT NOT NULL CHECK (skill_type IN ('teach', 'learn')),
            sort_order INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, skill_name, skill_type)
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            body TEXT NOT NULL,
            message_type TEXT NOT NULL DEFAULT 'text',
            proposal_id INTEGER DEFAULT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS swap_proposals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            proposer_id INTEGER NOT NULL,
            recipient_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            teach_skill TEXT NOT NULL DEFAULT '',
            learn_skill TEXT NOT NULL DEFAULT '',
            schedule_text TEXT NOT NULL,
            note TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined', 'completed', 'cancelled')),
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (proposer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reviewer_id INTEGER NOT NULL,
            reviewee_id INTEGER NOT NULL,
            proposal_id INTEGER DEFAULT NULL,
            rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
            skill_practiced TEXT NOT NULL DEFAULT '',
            comment TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (proposal_id) REFERENCES swap_proposals(id) ON DELETE SET NULL
        );
    SQL);

    // Migrate existing DB columns cleanly if upgrading from v1
    ensureColumn($pdo, 'users', 'role', "TEXT NOT NULL DEFAULT 'user'");
    ensureColumn($pdo, 'users', 'status', "TEXT NOT NULL DEFAULT 'active'");
    ensureColumn($pdo, 'swap_proposals', 'note', "TEXT NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'messages', 'message_type', "TEXT NOT NULL DEFAULT 'text'");
    ensureColumn($pdo, 'messages', 'proposal_id', 'INTEGER DEFAULT NULL');

    // Upgrade swap_proposals status CHECK constraint if needed
    $tableSql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='swap_proposals'")->fetchColumn();
    if ($tableSql !== '' && !str_contains($tableSql, "'completed'")) {
        $pdo->exec('PRAGMA foreign_keys = OFF;');
        $pdo->exec(<<<'SQL'
            BEGIN TRANSACTION;
            CREATE TABLE swap_proposals_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proposer_id INTEGER NOT NULL,
                recipient_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                teach_skill TEXT NOT NULL DEFAULT '',
                learn_skill TEXT NOT NULL DEFAULT '',
                schedule_text TEXT NOT NULL,
                note TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined', 'completed', 'cancelled')),
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (proposer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
            );
            INSERT INTO swap_proposals_new (id, proposer_id, recipient_id, title, teach_skill, learn_skill, schedule_text, note, status, updated_at)
            SELECT id, proposer_id, recipient_id, title, teach_skill, learn_skill, schedule_text, COALESCE(note, ''), status, updated_at FROM swap_proposals;
            DROP TABLE swap_proposals;
            ALTER TABLE swap_proposals_new RENAME TO swap_proposals;
            COMMIT;
        SQL);
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
    foreach ($cols as $col) {
        if ($col['name'] === $column) {
            return;
        }
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

function seedDatabase(PDO $pdo): void
{
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);

    $users = [
        [
            'id' => 1,
            'name' => 'Sam Upadhyay',
            'email' => 'sam@skillswap.local',
            'initials' => 'SU',
            'avatar_color' => '#0F766E',
            'availability' => 'Weekends and evenings',
            'location' => 'Kolkata',
            'bio' => 'Acoustic guitarist and hobbyist photographer looking to sharpen coding, UI design, and conversational French.',
            'teach' => ['Guitar', 'Photography'],
            'learn' => ['Python', 'UI design', 'French'],
        ],
        [
            'id' => 2,
            'name' => 'Aanya Sen',
            'email' => 'aanya@skillswap.local',
            'initials' => 'AS',
            'avatar_color' => '#503CE8',
            'availability' => 'Weekends, online',
            'location' => 'Online',
            'bio' => 'Software engineer who loves teaching Python fundamentals, loops, and clean problem solving. Always wanted to learn acoustic guitar.',
            'teach' => ['Python'],
            'learn' => ['Guitar'],
        ],
        [
            'id' => 3,
            'name' => 'Rohan Das',
            'email' => 'rohan@skillswap.local',
            'initials' => 'RD',
            'avatar_color' => '#0F766E',
            'availability' => 'Evenings, Kolkata',
            'location' => 'Kolkata',
            'bio' => 'Product designer specializing in design systems and prototyping. Excited to learn street and portrait photography.',
            'teach' => ['UI design'],
            'learn' => ['Photography'],
        ],
        [
            'id' => 4,
            'name' => 'Meera Banerjee',
            'email' => 'meera@skillswap.local',
            'initials' => 'MB',
            'avatar_color' => '#9A3412',
            'availability' => 'Weekdays, online',
            'location' => 'Online',
            'bio' => 'Bilingual French tutor focusing on everyday speaking confidence. Looking for help automating spreadsheets and Excel formulas.',
            'teach' => ['French'],
            'learn' => ['Excel', 'Photography'],
        ],
        [
            'id' => 5,
            'name' => 'Kabir Mehta',
            'email' => 'kabir@skillswap.local',
            'initials' => 'KM',
            'avatar_color' => '#4338CA',
            'availability' => 'Weekends, online',
            'location' => 'Online',
            'bio' => 'Data analyst teaching Python pandas and SQL workflows. Picking up chord progressions on guitar in my spare time.',
            'teach' => ['Python', 'SQL'],
            'learn' => ['Guitar'],
        ],
        [
            'id' => 6,
            'name' => 'Priya Nair',
            'email' => 'priya@skillswap.local',
            'initials' => 'PN',
            'avatar_color' => '#047857',
            'availability' => 'Evenings, online',
            'location' => 'Online',
            'bio' => 'UX researcher and Figma mentor helping beginners ship clean interfaces. Wanting to improve photo composition and editing.',
            'teach' => ['UI design', 'Figma'],
            'learn' => ['Photography'],
        ],
        [
            'id' => 7,
            'name' => 'Arjun Verma',
            'email' => 'arjun@skillswap.local',
            'initials' => 'AV',
            'avatar_color' => '#7C2D12',
            'availability' => 'Weekends and evenings',
            'location' => 'Bengaluru',
            'bio' => 'French literature graduate offering structured grammar and pronunciation sessions in exchange for beginner guitar lessons.',
            'teach' => ['French'],
            'learn' => ['Guitar'],
        ],
    ];

    $insertUser = $pdo->prepare(
        'INSERT INTO users (id, name, email, password_hash, initials, avatar_color, availability, location, bio, role, status)
         VALUES (:id, :name, :email, :password_hash, :initials, :avatar_color, :availability, :location, :bio, "user", "active")'
    );
    $insertSkill = $pdo->prepare(
        'INSERT INTO user_skills (user_id, skill_name, skill_type, sort_order)
         VALUES (:user_id, :skill_name, :skill_type, :sort_order)'
    );

    foreach ($users as $u) {
        $insertUser->execute([
            ':id' => $u['id'],
            ':name' => $u['name'],
            ':email' => $u['email'],
            ':password_hash' => $passwordHash,
            ':initials' => $u['initials'],
            ':avatar_color' => $u['avatar_color'],
            ':availability' => $u['availability'],
            ':location' => $u['location'],
            ':bio' => $u['bio'],
        ]);

        foreach ($u['teach'] as $idx => $skill) {
            $insertSkill->execute([
                ':user_id' => $u['id'],
                ':skill_name' => $skill,
                ':skill_type' => 'teach',
                ':sort_order' => $idx,
            ]);
        }
        foreach ($u['learn'] as $idx => $skill) {
            $insertSkill->execute([
                ':user_id' => $u['id'],
                ':skill_name' => $skill,
                ':skill_type' => 'learn',
                ':sort_order' => $idx,
            ]);
        }
    }

    $messages = [
        [2, 1, 'Hi! I saw you teach guitar. I can help with Python basics.', '2026-11-25 09:00:00'],
        [1, 2, 'That would be great. I have been stuck on loops.', '2026-11-25 09:03:00'],
        [2, 1, 'Happy to help. Want to swap one hour each?', '2026-11-25 09:05:00'],

        [1, 3, 'Hey Rohan! Would love to trade photography tips for UI design feedback.', '2026-11-24 18:10:00'],
        [3, 1, 'Sure, Sunday works', '2026-11-24 18:22:00'],

        [4, 1, 'Bonjour! Happy to practice French conversation together.', '2026-11-23 14:00:00'],
        [4, 1, 'Send me your Excel sheet', '2026-11-23 14:05:00'],
    ];

    $insertMsg = $pdo->prepare(
        'INSERT INTO messages (sender_id, receiver_id, body, created_at) VALUES (?, ?, ?, ?)'
    );
    foreach ($messages as $m) {
        $insertMsg->execute($m);
    }

    $insertProp = $pdo->prepare(
        'INSERT INTO swap_proposals (proposer_id, recipient_id, title, teach_skill, learn_skill, schedule_text, note, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertProp->execute([2, 1, '1 hr Python for 1 hr Guitar', 'Python', 'Guitar', 'Saturday, 4:00 PM, video call', 'Cover Python loops and basic guitar chords.', 'pending']);
    $insertProp->execute([3, 1, '1 hr UI design for 1 hr Photography', 'UI design', 'Photography', 'Sunday, 6:00 PM, video call', 'Review portfolio spacing and outdoor framing.', 'pending']);
    $insertProp->execute([4, 1, '1 hr French for 1 hr Excel', 'French', 'Excel', 'Wednesday, 7:00 PM, video call', 'Conversational French + pivot table formulas.', 'pending']);
}

function ensurePostSeedDefaults(PDO $pdo): void
{
    // Ensure demo Admin account exists
    $adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminExists === 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO users (name, email, password_hash, initials, avatar_color, availability, location, bio, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "admin", "active")'
        );
        $stmt->execute([
            'Maya Kapoor',
            'admin@skillswap.local',
            $adminPass,
            'MK',
            '#1E293B',
            'Weekdays, online',
            'Online',
            'SkillSwap Community Administrator.',
        ]);
    }

    // Seed realistic reviews if reviews table is empty
    $reviewCount = (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
    if ($reviewCount === 0) {
        $insRev = $pdo->prepare(
            'INSERT INTO reviews (reviewer_id, reviewee_id, proposal_id, rating, skill_practiced, comment, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $samples = [
            [1, 2, 1, 5, 'Python', 'Aanya explained loops and list comprehensions with crystal-clear examples!', '2026-11-20 18:00:00'],
            [3, 2, null, 5, 'Python', 'Patient teacher and super well-organized 1-hour session.', '2026-11-18 15:30:00'],
            [2, 1, 1, 5, 'Guitar', 'Sam got me playing my first three-chord progression in one call!', '2026-11-21 11:15:00'],
            [1, 3, 2, 5, 'UI design', 'Great eye for spacing, typography, and component hierarchy.', '2026-11-19 20:10:00'],
            [4, 6, null, 4, 'Figma', 'Helpful auto-layout walkthrough and practical shortcuts.', '2026-11-17 17:45:00'],
            [5, 4, null, 5, 'French', 'Warm, encouraging pronunciation practice from the very first minute.', '2026-11-16 12:00:00'],
        ];
        foreach ($samples as $row) {
            // Only insert if both users exist
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id IN (?, ?)');
            $check->execute([$row[0], $row[1]]);
            if ((int)$check->fetchColumn() === 2) {
                $insRev->execute($row);
            }
        }
    }
}
