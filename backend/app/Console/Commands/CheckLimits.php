<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Planer\LimitCheck;
use Illuminate\Console\Command;

/**
 * Lists, project by project, where existing data breaks the limits of the
 * planer file (docs/auftrag-m4.md §2.2). Changes nothing. Exit code 1 when
 * there are findings, so a deployment can stop on it.
 */
class CheckLimits extends Command
{
    protected $signature = 'planer:check-limits';

    protected $description = 'List every project that breaks a limit of the planer file (counts, description length); changes nothing';

    public function handle(LimitCheck $check): int
    {
        $projects = 0;
        $findings = 0;

        Project::query()->orderBy('id')->each(function (Project $project) use ($check, &$projects, &$findings): void {
            $found = $check->forProject($project);

            if ($found === []) {
                return;
            }

            $projects++;
            $findings += count($found);
            $this->components->warn("#{$project->getKey()} {$project->name}");

            foreach ($found as $finding) {
                $this->line("  {$finding['path']}: {$finding['message']}");
            }
        });

        if ($findings === 0) {
            $this->components->info(__('console.check_limits.clean'));

            return self::SUCCESS;
        }

        $this->components->error(__('console.check_limits.found', ['findings' => $findings, 'projects' => $projects]));

        return self::FAILURE;
    }
}
