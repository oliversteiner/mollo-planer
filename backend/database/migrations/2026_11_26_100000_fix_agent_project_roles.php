<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auftrag 58 C: an agent account is in every project only in the project role Agent, a person never. Memberships made
 * before the rule are put right: agents with another role get Agent, people with Agent get the default project role
 * (member, when the default is Agent itself). The numbers go to the log and to the output of the migration (deploy log).
 */
return new class extends Migration
{
    public function up(): void
    {
        $agentRoleId = DB::table('roles')->where('scope', 'project')->where('key', 'agent')->value('id');

        if ($agentRoleId === null) {
            return;
        }

        $defaultRoleId = DB::table('roles')->where('scope', 'project')->where('is_default', true)->where('id', '!=', $agentRoleId)->value('id')
            ?? DB::table('roles')->where('scope', 'project')->where('key', 'member')->value('id');

        $agentIds = DB::table('users')->where('is_agent', true)->select('id');
        $peopleIds = DB::table('users')->where('is_agent', false)->select('id');

        $agents = DB::table('project_user')
            ->whereIn('user_id', $agentIds)
            ->where('role_id', '!=', $agentRoleId)
            ->update(['role_id' => $agentRoleId]);

        $people = $defaultRoleId === null ? 0 : DB::table('project_user')
            ->whereIn('user_id', $peopleIds)
            ->where('role_id', $agentRoleId)
            ->update(['role_id' => $defaultRoleId]);

        $message = "Project roles put right (Auftrag 58): {$agents} agent membership(s) set to Agent, {$people} membership(s) of people moved off Agent.";
        Log::info($message, ['agents' => $agents, 'people' => $people]);

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, '  '.$message.PHP_EOL);
        }
    }

    /**
     * The old roles are not kept: there is nothing to go back to.
     */
    public function down(): void {}
};
