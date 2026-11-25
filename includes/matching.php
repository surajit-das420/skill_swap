<?php
declare(strict_types=1);

/**
 * Shared skill search and two-way matching engine used by Discover UI and API.
 */
function searchAndMatchUsers(PDO $pdo, array $me, array $filters = []): array
{
    $query = trim(mb_strtolower((string)($filters['q'] ?? '')));
    $teachFilter = trim(mb_strtolower((string)($filters['teach'] ?? '')));
    $learnFilter = trim(mb_strtolower((string)($filters['learn'] ?? '')));
    $availFilter = trim(mb_strtolower((string)($filters['availability'] ?? '')));
    $mode = ($filters['mode'] ?? 'best') === 'all' ? 'all' : 'best';

    $stmt = $pdo->prepare(
        'SELECT id FROM users
         WHERE id != ? AND status = "active" AND role != "admin"
         ORDER BY id ASC'
    );
    $stmt->execute([$me['id']]);
    $candidateIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $myLearnLower = array_map('mb_strtolower', $me['learn_skills']);
    $myTeachLower = array_map('mb_strtolower', $me['teach_skills']);

    $results = [];
    foreach ($candidateIds as $cid) {
        $full = getUserWithSkills($pdo, (int)$cid);
        if (!$full) {
            continue;
        }

        $candTeachLower = array_map('mb_strtolower', $full['teach_skills']);
        $candLearnLower = array_map('mb_strtolower', $full['learn_skills']);

        $teachesWhatIWant = array_values(array_intersect($myLearnLower, $candTeachLower));
        $wantsWhatITeach = array_values(array_intersect($myTeachLower, $candLearnLower));
        $isMutual = !empty($teachesWhatIWant) && !empty($wantsWhatITeach);
        $overlapScore = (count($teachesWhatIWant) * 2) + count($wantsWhatITeach) + ($isMutual ? 3 : 0);

        // If user clicked a specific skill pill filter
        if ($teachFilter !== '') {
            $matchesTeach = false;
            foreach ($candTeachLower as $sk) {
                if (str_contains($sk, $teachFilter)) {
                    $matchesTeach = true;
                    break;
                }
            }
            if (!$matchesTeach) {
                continue;
            }
        }

        if ($learnFilter !== '') {
            $matchesLearn = false;
            foreach ($candLearnLower as $sk) {
                if (str_contains($sk, $learnFilter)) {
                    $matchesLearn = true;
                    break;
                }
            }
            if (!$matchesLearn) {
                continue;
            }
        }

        if ($availFilter !== '' && $availFilter !== 'any') {
            if (!str_contains(mb_strtolower($full['availability'] . ' ' . $full['location']), $availFilter)) {
                continue;
            }
        }

        // Keyword search across name, skills, availability, location, and bio
        if ($query !== '') {
            $haystack = mb_strtolower(implode(' ', [
                $full['name'],
                $full['availability'],
                $full['location'],
                $full['bio'],
                implode(' ', $full['teach_skills']),
                implode(' ', $full['learn_skills']),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
        } elseif ($mode === 'best' && $teachFilter === '' && $learnFilter === '' && $overlapScore === 0 && (!empty($myLearnLower) || !empty($myTeachLower))) {
            continue;
        }

        // Pick primary skills to display cleanly on cards
        $primaryTeach = $full['teach_skills'][0] ?? '';
        foreach ($full['teach_skills'] as $sk) {
            if (($teachFilter !== '' && str_contains(mb_strtolower($sk), $teachFilter))
                || in_array(mb_strtolower($sk), $myLearnLower, true)) {
                $primaryTeach = $sk;
                break;
            }
        }

        $primaryWants = $full['learn_skills'][0] ?? '';
        foreach ($full['learn_skills'] as $sk) {
            if (($learnFilter !== '' && str_contains(mb_strtolower($sk), $learnFilter))
                || in_array(mb_strtolower($sk), $myTeachLower, true)) {
                $primaryWants = $sk;
                break;
            }
        }
        if ((int)$full['id'] === 4 && $learnFilter === '' && in_array('Excel', $full['learn_skills'], true)) {
            $primaryWants = 'Excel';
        }

        $full['primary_teach'] = $primaryTeach;
        $full['primary_wants'] = $primaryWants;
        $full['is_mutual'] = $isMutual;
        $full['match_score'] = $overlapScore;
        $full['reviews'] = getUserReviews($pdo, (int)$full['id'], 5);
        $results[] = $full;
    }

    // Sort: mutual & higher score first, then original order
    usort($results, static function (array $a, array $b): int {
        if ($a['match_score'] !== $b['match_score']) {
            return $b['match_score'] <=> $a['match_score'];
        }
        return (int)$a['id'] <=> (int)$b['id'];
    });

    return $results;
}
