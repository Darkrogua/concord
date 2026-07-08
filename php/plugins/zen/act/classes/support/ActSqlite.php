<?php namespace Zen\Act\Classes\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite-база одного акта: storage/acts/{uuid}/act.sqlite.
 */
class ActSqlite
{
    private string $connection_name;

    public function __construct(
        private string $act_id,
    ) {
        $this->connect();
        $this->ensureSchema();
    }

    public static function make(string $act_id): self
    {
        return new self($act_id);
    }

    public function path(): string
    {
        return ActStorage::make()->actDirectory($this->act_id).'/act.sqlite';
    }

    public function connectionName(): string
    {
        return $this->connection_name;
    }

    public function connection(): ConnectionInterface
    {
        return DB::connection($this->connection_name);
    }

    public function query(string $table): QueryBuilder
    {
        return $this->connection()->table($table);
    }

    private function connect(): void
    {
        $db_path = $this->path();
        $directory = dirname($db_path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! file_exists($db_path)) {
            touch($db_path);
        }

        $this->connection_name = 'act_sqlite_'.hash('sha256', $db_path);

        DB::purge($this->connection_name);
        config(['database.connections.'.$this->connection_name => [
            'driver' => 'sqlite',
            'database' => $db_path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::reconnect($this->connection_name);
    }

    private function ensureSchema(): void
    {
        $schema = Schema::connection($this->connection_name);

        if (! $schema->hasTable('blocks')) {
            $schema->create('blocks', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->text('data');
                $table->integer('sort_order')->unsigned()->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('hash', 64);
                $table->index('name');
                $table->index('sort_order');
                $table->index('created_at');
            });
        } elseif (! $schema->hasColumn('blocks', 'sort_order')) {
            $schema->table('blocks', function (Blueprint $table): void {
                $table->integer('sort_order')->unsigned()->nullable();
                $table->index('sort_order');
            });
            $this->backfillSortOrder();
        }

        if (! $schema->hasTable('log')) {
            $schema->create('log', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('action');
                $table->string('block_id')->nullable();
                $table->text('data');
                $table->string('hash', 64);
                $table->string('chain', 64)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('block_id');
                $table->index('created_at');
            });
        }

        if (! $schema->hasTable('access_meta')) {
            $schema->create('access_meta', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->text('value');
            });
        }

        if (! $schema->hasTable('access')) {
            $schema->create('access', function (Blueprint $table): void {
                $table->string('resource_type');
                $table->string('resource_id');
                $table->string('login');
                $table->string('role');
                $table->primary(['resource_type', 'resource_id', 'login']);
                $table->index('login');
            });
        }

        if (! $schema->hasTable('user_tags')) {
            $schema->create('user_tags', function (Blueprint $table): void {
                $table->string('login');
                $table->string('tag', 30);
                $table->timestamp('created_at')->nullable();
                $table->primary(['login', 'tag']);
                $table->index('login');
            });
        }

        if (! $schema->hasTable('snapshots')) {
            $schema->create('snapshots', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('created_at');
                $table->string('kind');
                $table->string('scope');
                $table->text('payload');
                $table->integer('log_head_id')->unsigned()->nullable();
                $table->string('envelope_hash', 64);
                $table->string('prev_hash', 64)->nullable();
                $table->string('hash', 64);
                $table->integer('size_bytes')->unsigned()->default(0);
                $table->index('created_at');
            });
        }
    }

    private function backfillSortOrder(): void
    {
        $rows = $this->query('blocks')->orderBy('created_at')->orderBy('id')->get();
        $position = 1;
        foreach ($rows as $row) {
            $this->query('blocks')->where('id', (string) $row->id)->update([
                'sort_order' => $position,
            ]);
            $position++;
        }
    }
}
