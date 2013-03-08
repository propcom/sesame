<?php

namespace Fuel\Migrations;

class Users
{
	public function up()
	{
		\DBUtil::create_table('sesame__users',
		[
			'id' => [
				'type' => 'int',
				'unsigned' => true,
				'auto_increment' => true
			],
			'username' => [
				'type' => 'varchar',
				'constraint' => 24,
			],
			'password' => [
				'type' => 'char',
				'constraint' => 128,
				'null' => true,
			],
			'last_login' => [
				'type' => 'datetime',
				'null' => true
			],
			'created_at' => [
				'type' => 'timestamp',
				'default' => \DB::expr('CURRENT_TIMESTAMP')
			],
			'updated_at' => [
				'type' => 'datetime',
				'null' => true
			],
		], [ 'id' ], true, 'InnoDB', 'utf8_unicode_ci');

		\DBUtil::create_index('sesame__users', [ 'username' ], 'UQ_username', 'unique');
	}

	public function down()
	{
		\DBUtil::drop_table('sesame__users');
	}
}
