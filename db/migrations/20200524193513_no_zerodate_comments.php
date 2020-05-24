<?php

use Phinx\Migration\AbstractMigration;

class NoZerodateComments extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE comments MODIFY AddedTime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    public function down() {
        $this->execute("ALTER TABLE comment MODIFY AddedTime datetime NOT NULL DEFAULT '0000-00-00 00:00:00'");
    }
}

