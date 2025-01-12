<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_a_%d';
    protected const ROLE_KEY = 'artist_role';

    protected array $role;
    protected int $groupId; // torrent or request context
    protected int $userId; // who is manipulating the torrents_artists or requests_artists tables

    public function __construct() {
        $role = self::$cache->get_value(self::ROLE_KEY);
        if ($role === false) {
            self::$db->prepared_query("
                SELECT slug, artist_role_id, sequence, name, title, collection
                FROM artist_role
                ORDER BY artist_role_id
            ");
            $role = self::$db->to_array('slug', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::ROLE_KEY, $role, 86400 * 30);
        }
        $this->role = $role;
    }

    public function create(string $name): array {
        self::$db->begin_transaction();
        self::$db->prepared_query('
            INSERT INTO artists_group (Name)
            VALUES (?)
            ', $name
        );
        $artistId = self::$db->inserted_id();

        self::$db->prepared_query('
            INSERT INTO artists_alias (ArtistID, Name)
            VALUES (?, ?)
            ', $artistId, $name
        );
        $aliasId = self::$db->inserted_id();
        self::$db->commit();

        self::$cache->increment('stats_artist_count');

        return [$artistId, $aliasId];
    }

    public function findById(int $artistId): ?\Gazelle\Artist {
        $key = sprintf(self::ID_KEY, $artistId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ArtistID FROM artists_group WHERE ArtistID = ?
                ", $artistId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Artist($id) : null;
    }

    public function findByIdAndRevision(int $artistId, int $revisionId): ?\Gazelle\Artist {
        $id = (int)self::$db->scalar("
            SELECT ag.ArtistID
            FROM artists_group ag
            INNER JOIN wiki_artists wa ON (wa.PageID = ag.ArtistID)
            WHERE wa.PageID = ?
                AND wa.RevisionID = ?
            ", $artistId, $revisionId
        );
        return $id ? new \Gazelle\Artist($id, $revisionId) : null;
    }

    public function findByName(string $name): ?\Gazelle\Artist {
        return $this->findById((int)self::$db->scalar("
            SELECT ArtistID FROM artists_group WHERE Name = ?
            ", trim($name)
        ));
    }

    public function findByNameAndRevision(string $name, int $revisionId): ?\Gazelle\Artist {
        $id = (int)self::$db->scalar("
            SELECT ArtistID
            FROM artists_group
            WHERE Name = ?
                AND RevisionID = ?
            ", trim($name), $revisionId
        );
        return $id ? new \Gazelle\Artist($id, $revisionId) : null;
    }

    public function findByAliasId(int $aliasId): ?\Gazelle\Artist {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT aa.ArtistID
                FROM artists_alias aa
                INNER JOIN artists_group ag USING (ArtistID)
                WHERE aa.AliasID = ?
                ", $aliasId
            )
        );
    }

    public function findByRedirectId(int $redirectId): ?\Gazelle\Artist {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT ArtistID FROM artists_alias WHERE AliasID = ?
                ", $redirectId
            )
        );
    }

    public function findRandom(): ?\Gazelle\Artist {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT r1.artist_id
                FROM artist_usage r1,
                (SELECT rand() * max(artist_id) AS artist_id FROM artist_usage) AS r2
                WHERE r1.artist_id >= r2.artist_id
                    AND r1.role in ('1', '3', '4', '5', '6', '7')
                GROUP BY r1.artist_id
                HAVING sum(r1.uses) >= ?
                LIMIT 1
                ", RANDOM_ARTIST_MIN_ENTRIES
            )
        );
    }

    public function aliasUseTotal(int $aliasId): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM artists_alias AS aa
            INNER JOIN artists_alias AS aa2 USING (ArtistID)
            WHERE aa.AliasID = ?
            ", $aliasId
        );
    }

    public function tgroupList(int $aliasId, \Gazelle\Manager\TGroup $tgMan): array {
        self::$db->prepared_query("
            SELECT GroupID FROM torrents_artists WHERE AliasID = ?
            ", $aliasId
        );
        return array_filter(
            array_map(
                fn($id) => $tgMan->findById($id),
                self::$db->collect(0, false)
            ),
            fn ($tgroup) => !empty($tgroup)
        );
    }

    public function fetchArtistIdAndAliasId(string $name): ?array {
        self::$db->prepared_query('
            SELECT AliasID, ArtistID, Redirect, Name
            FROM artists_alias
            WHERE Name = ?
            ', $name
        );
        while ([$aliasId, $artistId, $redirect, $foundAliasName] = self::$db->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($name, $foundAliasName)) {
                if ($redirect) {
                    $aliasId = $redirect;
                }
                break;
            }
        }
        return $aliasId ? [$artistId, $aliasId] : $this->create($name);
    }

    public function setGroupId(int $groupId): static {
        $this->groupId = $groupId;
        return $this;
    }

    public function setUserId(int $userId): static {
        $this->userId = $userId;
        return $this;
    }

    public function sectionName(int $sectionId): ?string {
        return (new \Gazelle\ReleaseType)->findExtendedNameById($sectionId);
    }

    public function sectionLabel(int $sectionId): string {
        return strtolower(str_replace(' ', '_', $this->sectionName($sectionId)));
    }

    public function sectionTitle(int $sectionId): string {
        return (new \Gazelle\ReleaseType)->sectionTitle($sectionId);
    }

    public function addToRequest(int $artistId, int $aliasId, int $role): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO requests_artists
                   (RequestID, ArtistID, AliasID, artist_role_id, Importance)
            VALUES (?,         ?,        ?,       ?,              ?)
            ", $this->groupId, $artistId, $aliasId, $role, (string)$role
        );
        return self::$db->affected_rows();
    }
}
