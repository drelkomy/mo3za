<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $indexesCache = [];

    protected function getIndexes(string $table): array
    {
        if (!isset($this->indexesCache[$table])) {
            $results = DB::select("SHOW INDEX FROM `{$table}`");
            $this->indexesCache[$table] = collect($results)->pluck('Key_name')->toArray();
        }

        return $this->indexesCache[$table];
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        return in_array($indexName, $this->getIndexes($table));
    }

    public function up(): void
    {
        // Users Table (أساسي لتصفية المستخدمين النشطين + نوع المستخدم في الفلاتر)
        if (!$this->indexExists('users', 'users_is_active_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('is_active', 'users_is_active_index');
            });
        }

        if (!$this->indexExists('users', 'users_user_type_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('user_type', 'users_user_type_index');
            });
        }

        // Subscriptions Table (لتسريع الفلترة حسب الاشتراك والحالة والباقة)
        if (!$this->indexExists('subscriptions', 'subscriptions_user_status_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['user_id', 'status'], 'subscriptions_user_status_index');
            });
        }

        if (!$this->indexExists('subscriptions', 'subscriptions_package_id_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('package_id', 'subscriptions_package_id_index');
            });
        }

        if (!$this->indexExists('subscriptions', 'subscriptions_start_date_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('start_date', 'subscriptions_start_date_index');
            });
        }

        // Tasks Table (الأكثر أهمية - البحث عن المهام حسب المستلم والمنشئ والفريق)
        if (!$this->indexExists('tasks', 'tasks_receiver_id_index')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index('receiver_id', 'tasks_receiver_id_index');
            });
        }

        if (!$this->indexExists('tasks', 'tasks_creator_status_index')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['creator_id', 'status'], 'tasks_creator_status_index');
            });
        }

        if (!$this->indexExists('tasks', 'tasks_team_status_index')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['team_id', 'status'], 'tasks_team_status_index');
            });
        }

        if (!$this->indexExists('tasks', 'tasks_subscription_status_index')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['subscription_id', 'status'], 'tasks_subscription_status_index');
            });
        }

        // Teams Table (للبحث عن الفرق حسب المالك)
        if (!$this->indexExists('teams', 'teams_owner_id_index')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->index('owner_id', 'teams_owner_id_index');
            });
        }

        // Join Requests Table (للبحث المركب في طلبات الانضمام)
        if (!$this->indexExists('join_requests', 'join_requests_user_team_status_index')) {
            Schema::table('join_requests', function (Blueprint $table) {
                $table->index(['user_id', 'team_id', 'status'], 'join_requests_user_team_status_index');
            });
        }

        // Packages Table (للبحث في الباقات النشطة والتجريبية)
        if (!$this->indexExists('packages', 'packages_active_trial_index')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->index(['is_active', 'is_trial'], 'packages_active_trial_index');
            });
        }

        // 🔍 Payments Table – للبحث في المدفوعات مع الترتيب الزمني
        if (!$this->indexExists('payments', 'payments_user_status_created_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'created_at'], 'payments_user_status_created_index');
            });
        }

        if (!$this->indexExists('payments', 'payments_package_id_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('package_id', 'payments_package_id_index');
            });
        }

        // Task Stages Table (للبحث في مراحل المهام)
        if (!$this->indexExists('task_stages', 'task_stages_task_status_index')) {
            Schema::table('task_stages', function (Blueprint $table) {
                $table->index(['task_id', 'status'], 'task_stages_task_status_index');
            });
        }

        // 🔍 Notifications Table – لتحسين البحث في الإشعارات حسب حالة القراءة
        if (!$this->indexExists('notifications', 'notifications_notifiable_read_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_index');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users' => ['users_is_active_index', 'users_user_type_index'],
            'subscriptions' => ['subscriptions_user_status_index', 'subscriptions_package_id_index', 'subscriptions_start_date_index'],
            'tasks' => ['tasks_receiver_id_index', 'tasks_creator_status_index', 'tasks_team_status_index', 'tasks_subscription_status_index'],
            'teams' => ['teams_owner_id_index'],
            'join_requests' => ['join_requests_user_team_status_index'],
            'packages' => ['packages_active_trial_index'],
            'payments' => ['payments_user_status_created_index', 'payments_package_id_index'],
            'task_stages' => ['task_stages_task_status_index'],
            'notifications' => ['notifications_notifiable_read_index']
        ];

        foreach ($tables as $tableName => $indexes) {
            $existingIndexes = $this->getIndexes($tableName);

            Schema::table($tableName, function (Blueprint $table) use ($indexes, $existingIndexes) {
                foreach ($indexes as $index) {
                    if (in_array($index, $existingIndexes)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }
    }
};