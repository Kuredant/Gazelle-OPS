<?php

use Phinx\Migration\AbstractMigration;

class DropDeletedTorrentsFiles extends AbstractMigration {
    public function up() {
        $this->table('deleted_torrents_files')->drop()->update();
    }

    public function down() {
        $this->table('deleted_torrents_files', [
                'id' => false,
                'primary_key' => ['TorrentID'],
                'encoding' => 'utf8',
                'collation' => 'utf8_general_ci',
                'row_format' => 'DYNAMIC',
            ])
            ->addColumn('TorrentID', 'integer', [
                'null' => false,
                'limit' => '10',
            ])
            ->addColumn('File', 'blob', [
                'null' => false,
                'limit' => MysqlAdapter::BLOB_MEDIUM,
            ])
            ->create();
    }
}