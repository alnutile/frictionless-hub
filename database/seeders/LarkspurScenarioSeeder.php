<?php

namespace Database\Seeders;

use App\Enums\Frequency;
use App\Enums\Phase;
use App\Enums\Role;
use App\Enums\StoryMapKind;
use App\Models\Initiative;
use App\Models\StoryMapItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The benchmark scenario: Larkspur Labs, a fictional ~140-developer SaaS company
 * that sells field-service scheduling software. Every name, team and problem is
 * invented for this project. Nothing comes from the book or workbook.
 *
 * Re-running is safe: the scenario initiative is rebuilt from scratch.
 * Extend it with a new section whenever a walkthrough activity ships.
 */
class LarkspurScenarioSeeder extends Seeder
{
    public const INITIATIVE_NAME = 'Larkspur DevEx 2027';

    public const EMAIL_DOMAIN = 'larkspur.test';

    /**
     * @var array<string, User>
     */
    private array $people = [];

    public function run(): void
    {
        DB::transaction(function () {
            $this->seedPeople();

            Initiative::where('name', self::INITIATIVE_NAME)->get()->each->delete();

            $initiative = $this->people['priya']->initiatives()->create([
                'name' => self::INITIATIVE_NAME,
                'scope' => 'All product engineering (~140 developers)',
                'description' => "Larkspur Labs builds field-service scheduling software: a React web app, iOS and Android apps, and a Rails monolith (\"Hedgehog\") that still handles billing.\n\nLeadership wants faster delivery without burning people out. This initiative starts by finding where developers lose time.",
                'phase' => Phase::Discover,
            ]);
            $initiative->seedDefaults();

            $this->seedWorkflow($initiative);
            $this->seedFriction($initiative);
            $this->seedStoryMap($initiative);
            $this->seedRatings($initiative);
            $this->seedListeningTour($initiative);
        });
    }

    private function seedPeople(): void
    {
        $cast = [
            'priya' => ['Priya Raman', Role::Facilitator],   // DevEx lead, owns the initiative
            'marcus' => ['Marcus Bell', Role::Facilitator],  // Platform engineering manager
            'dana' => ['Dana Ortiz', Role::Viewer],          // VP Engineering
            'sam' => ['Sam Okafor', Role::Viewer],           // Senior web developer
            'lin' => ['Lin Zhao', Role::Viewer],             // Mobile developer, new hire
            'jo' => ['Jo Haddad', Role::Viewer],             // Backend developer on Hedgehog
            'theo' => ['Theo Brandt', Role::Viewer],         // Payments developer
            'ama' => ['Ama Mensah', Role::Viewer],           // Platform engineer
        ];

        foreach ($cast as $handle => [$name, $role]) {
            $this->people[$handle] = User::updateOrCreate(
                ['email' => "{$handle}@".self::EMAIL_DOMAIN],
                ['name' => $name, 'role' => $role, 'password' => 'password', 'email_verified_at' => now()],
            );
        }
    }

    private function seedWorkflow(Initiative $initiative): void
    {
        $tools = [
            'Planning' => [['Linear'], ['Notion'], ['Figma']],
            'Coding' => [['VS Code'], ['GitHub'], ['Cursor', true], ['Copilot', true], ['Local Docker stack']],
            'Testing' => [['Jest'], ['Playwright'], ['RSpec'], ['Detox (mobile)']],
            'Reviewing' => [['GitHub pull requests'], ['CODEOWNERS'], ['AI review bot', true]],
            'Building' => [['GitHub Actions'], ['Buildkite (Hedgehog)'], ['Fastlane']],
            'Deployment' => [['Argo CD'], ['Kubernetes'], ['App Store Connect'], ['Preview env request form']],
            'Maintenance' => [['Datadog'], ['PagerDuty'], ['Sentry']],
        ];

        $notes = [
            'Planning' => 'Web and mobile plan in Linear; Hedgehog work still arrives as Notion docs.',
            'Coding' => 'AI assistants are used heavily on web, barely on Hedgehog.',
            'Deployment' => 'Web deploys continuously. Mobile ships on a two-week release train.',
        ];

        $aiStages = ['Coding', 'Reviewing'];

        foreach ($initiative->stages as $stage) {
            $stage->update([
                'notes' => $notes[$stage->name] ?? null,
                'ai_assisted' => in_array($stage->name, $aiStages, true),
            ]);

            foreach ($tools[$stage->name] ?? [] as $tool) {
                $stage->tools()->create(['name' => $tool[0], 'is_ai' => $tool[1] ?? false]);
            }
        }
    }

    private function seedFriction(Initiative $initiative): void
    {
        $stages = $initiative->stages()->pluck('id', 'name');
        $dimensions = $initiative->dimensions()->pluck('id', 'name');

        $points = [
            ['Hedgehog CI suite takes ~38 minutes', 'Building', Frequency::MultipleDaily, 5, 'Backend devs on Hedgehog', ['Feedback loops', 'Flow state'],
                'Developers start a run, switch to another task, and lose the thread. Re-runs for flaky specs double it.'],
            ['Preview environments take a day to get', 'Deployment', Frequency::Weekly, 4, 'Web and payments teams', ['Feedback loops'],
                'Requested through a form that the platform team works through manually.'],
            ['PRs wait on reviewers listed in a stale CODEOWNERS file', 'Reviewing', Frequency::Daily, 4, 'Most developers', ['Flow state', 'Feedback loops'],
                'Several owners left the company; PRs sit until someone notices.'],
            ['Local Docker stack breaks after OS updates', 'Coding', Frequency::Monthly, 3, 'Mac users, especially new hires', ['Cognitive load'],
                'Fixes live in a Slack thread rather than the docs.'],
            ['Mobile release needs three manual sign-offs', 'Deployment', Frequency::Weekly, 4, 'Mobile developers', ['Flow state'],
                'QA, product and an engineering manager each approve in a different tool.'],
            ['No runbooks for payment-service alerts', 'Maintenance', Frequency::Weekly, 5, 'Payments on-call', ['Cognitive load'],
                'On-call engineers reverse-engineer the service at 2am.'],
            ['AI assistant suggestions ignore the internal SDK', 'Coding', Frequency::Daily, 2, 'Web developers', ['Cognitive load'],
                'Suggested code calls public APIs that the internal SDK wraps, so it gets rewritten in review.'],
            ['Requirements for Hedgehog work live in scattered Notion docs', 'Planning', Frequency::Weekly, 3, 'Hedgehog team', ['Cognitive load']],
            ['Flaky Detox tests block mobile merges', 'Testing', Frequency::Daily, 4, 'Mobile developers', ['Feedback loops', 'Flow state']],
            ['Unclear who approves cross-team API changes', null, Frequency::Monthly, 3, 'Senior developers', ['Cognitive load']],
        ];

        foreach ($points as $point) {
            [$title, $stage, $frequency, $pain, $affected, $dims] = $point;

            $model = $initiative->frictionPoints()->make([
                'title' => $title,
                'description' => $point[6] ?? null,
                'workflow_stage_id' => $stage ? $stages[$stage] : null,
                'frequency' => $frequency,
                'pain' => $pain,
                'affected' => $affected,
            ]);
            $model->created_by = $this->people['priya']->id;
            $model->save();
            $model->dimensions()->sync(array_map(fn (string $d) => $dimensions[$d], $dims));
        }
    }

    private function seedStoryMap(Initiative $initiative): void
    {
        $map = [
            'Pick up work' => [
                'Find the ticket' => [['Open Linear board', StoryMapKind::Story], ['Hedgehog tickets live in Notion', StoryMapKind::Gap]],
                'Understand the ask' => [['Read spec and designs', StoryMapKind::Story], ['Requirements scattered', StoryMapKind::Friction], ['Product team', StoryMapKind::Team]],
            ],
            'Build the change' => [
                'Start local env' => [['docker compose up', StoryMapKind::Story], ['Breaks after OS updates', StoryMapKind::Friction], ['Local Docker stack', StoryMapKind::Tool]],
                'Write code' => [['Code with AI assistant', StoryMapKind::Story], ['Cursor / Copilot', StoryMapKind::Tool], ['Ignores internal SDK', StoryMapKind::Friction]],
            ],
            'Get it reviewed' => [
                'Open PR' => [['Push branch and open PR', StoryMapKind::Story], ['CI: 38 min on Hedgehog', StoryMapKind::Friction]],
                'Wait for review' => [['Request reviewers', StoryMapKind::Story], ['Stale CODEOWNERS', StoryMapKind::Friction], ['All product teams', StoryMapKind::Team]],
            ],
            'Ship it' => [
                'Test in preview' => [['Request preview env', StoryMapKind::Story], ['Takes a day', StoryMapKind::Friction], ['No self-service', StoryMapKind::Gap]],
                'Release' => [['Merge and deploy', StoryMapKind::Story], ['Argo CD', StoryMapKind::Tool], ['Mobile: 3 sign-offs', StoryMapKind::Friction]],
            ],
        ];

        $activityPosition = 0;
        foreach ($map as $activityTitle => $tasks) {
            $activity = $initiative->storyMapItems()->create([
                'level' => StoryMapItem::LEVEL_ACTIVITY, 'title' => $activityTitle, 'position' => $activityPosition++,
            ]);

            $taskPosition = 0;
            foreach ($tasks as $taskTitle => $cards) {
                $task = $initiative->storyMapItems()->create([
                    'parent_id' => $activity->id, 'level' => StoryMapItem::LEVEL_TASK, 'title' => $taskTitle, 'position' => $taskPosition++,
                ]);

                foreach ($cards as $cardPosition => [$cardTitle, $kind]) {
                    $initiative->storyMapItems()->create([
                        'parent_id' => $task->id, 'level' => StoryMapItem::LEVEL_CARD, 'kind' => $kind,
                        'title' => $cardTitle, 'position' => $cardPosition,
                    ]);
                }
            }
        }
    }

    private function seedRatings(Initiative $initiative): void
    {
        // Scores per rater, 1 = excellent … 5 = severe. null = didn't rate.
        $groups = [
            'Getting started' => [
                'Laptop setup to first commit' => ['sam' => 2, 'lin' => 5, 'jo' => 3, 'theo' => 3, 'ama' => 2],
                'Getting access to systems' => ['sam' => 3, 'lin' => 4, 'jo' => 3, 'theo' => 4, 'ama' => 2],
            ],
            'Daily coding loop' => [
                'Local build and reload speed' => ['sam' => 2, 'lin' => 3, 'jo' => 4, 'theo' => 2, 'ama' => 2],
                'Running tests locally' => ['sam' => 2, 'lin' => 4, 'jo' => 5, 'theo' => 3, 'ama' => null],
                'Finding internal docs' => ['sam' => 4, 'lin' => 5, 'jo' => 4, 'theo' => 4, 'ama' => 3],
            ],
            'Shipping' => [
                'CI reliability' => ['sam' => 3, 'lin' => 4, 'jo' => 5, 'theo' => 3, 'ama' => 2],
                'Preview environments' => ['sam' => 4, 'lin' => null, 'jo' => 3, 'theo' => 5, 'ama' => 3],
                'Release process' => ['sam' => 1, 'lin' => 5, 'jo' => 3, 'theo' => 2, 'ama' => 2],
            ],
            'Working together' => [
                'Code review turnaround' => ['sam' => 4, 'lin' => 4, 'jo' => 4, 'theo' => 3, 'ama' => 3],
                'Changing another team’s API' => ['sam' => 3, 'lin' => 3, 'jo' => 4, 'theo' => 4, 'ama' => 4],
            ],
        ];

        $groupPosition = 0;
        foreach ($groups as $groupName => $items) {
            $group = $initiative->ratingGroups()->create(['name' => $groupName, 'position' => $groupPosition++]);

            $itemPosition = 0;
            foreach ($items as $label => $scores) {
                $item = $group->items()->create(['label' => $label, 'position' => $itemPosition++]);

                foreach (array_filter($scores) as $handle => $score) {
                    $item->ratings()->create(['user_id' => $this->people[$handle]->id, 'score' => $score]);
                }
            }
        }
    }

    private function seedListeningTour(Initiative $initiative): void
    {
        $initiative->update(['interview_target' => 14]);

        $areas = [];
        $areaCategory = $initiative->areaCategory();
        foreach (['Web', 'Mobile', 'Platform', 'Payments', 'Hedgehog (legacy)'] as $position => $name) {
            $areas[$name] = $areaCategory->values()->create(['name' => $name, 'position' => $position])->id;
        }

        $groups = [
            'Experience' => ['Early career', 'Mid-level', 'Senior / staff'],
            'Time at Larkspur' => ['Under 1 year', '1–3 years', 'Over 3 years'],
            'AI tool use' => ['Rarely', 'Weekly', 'Daily'],
        ];

        $values = [];
        foreach (array_keys($groups) as $position => $groupName) {
            $category = $initiative->segmentCategories()->create(['name' => $groupName, 'position' => $position + 1]);
            foreach ($groups[$groupName] as $valuePosition => $valueName) {
                $values[$valueName] = $category->values()->create(['name' => $valueName, 'position' => $valuePosition])->id;
            }
        }

        $checklist = [];
        foreach ([
            'Confirm the time and send the video link',
            'Explain the purpose and how notes will be used',
            'Review the friction logged for their area',
            'Prepare two or three questions specific to their work',
            'Ask whether recording is okay',
            'Open the notes template',
        ] as $position => $label) {
            $checklist[] = $initiative->checklistItems()->create(['label' => $label, 'position' => $position])->id;
        }

        // [name, role, area, [experience, tenure, ai], status, reason, prep items done]
        // Payments has a single person and nobody early-career is on Platform: deliberate gaps.
        // Noor has no "Time at Larkspur" value, so the totals check flags Mobile.
        $people = [
            ['Sam Okafor', 'Senior web developer', 'Web', ['Senior / staff', 'Over 3 years', 'Daily'], 'interviewed', 'you know the web build pipeline better than anyone', 6],
            ['Riya Desai', 'Web developer', 'Web', ['Early career', 'Under 1 year', 'Daily'], 'interviewed', 'a recent hire sees our setup with fresh eyes', 6],
            ['Marta Kowalski', 'Frontend lead', 'Web', ['Senior / staff', '1–3 years', 'Weekly'], 'scheduled', 'you lead most of the frontend reviews', 4],
            ['Lin Zhao', 'iOS developer', 'Mobile', ['Early career', 'Under 1 year', 'Weekly'], 'interviewed', 'you joined recently and work on the mobile release train', 6],
            ['Diego Alvarez', 'Android developer', 'Mobile', ['Mid-level', '1–3 years', 'Rarely'], 'scheduled', 'flaky Detox tests seem to hit Android hardest', 3],
            ['Noor Haddad', 'Mobile QA engineer', 'Mobile', ['Mid-level', null, 'Rarely'], 'invited', 'you see every mobile release sign-off', 1],
            ['Ama Mensah', 'Platform engineer', 'Platform', ['Mid-level', '1–3 years', 'Daily'], 'interviewed', 'you handle most preview-environment requests', 6],
            ['Ben Fischer', 'Staff platform engineer', 'Platform', ['Senior / staff', 'Over 3 years', 'Weekly'], 'scheduled', 'you designed much of the CI setup', 2],
            ['Theo Brandt', 'Payments developer', 'Payments', ['Senior / staff', 'Over 3 years', 'Rarely'], 'invited', 'payments on-call has the least documentation', 1],
            ['Jo Haddad', 'Backend developer', 'Hedgehog (legacy)', ['Senior / staff', 'Over 3 years', 'Rarely'], 'interviewed', 'you work in Hedgehog every day', 6],
            ['Kenji Mori', 'Backend developer', 'Hedgehog (legacy)', ['Early career', 'Under 1 year', 'Weekly'], 'candidate', 'you are newest to the Hedgehog codebase', 0],
            ['Grace Lin', 'Backend developer', 'Hedgehog (legacy)', ['Mid-level', '1–3 years', 'Weekly'], 'declined', 'you moved onto Hedgehog last year', 0],
            ['Omar Shah', 'Engineering manager', null, ['Senior / staff', 'Over 3 years', 'Weekly'], 'candidate', 'you manage across two product teams', 0],
        ];

        foreach ($people as [$name, $role, $area, $groupValues, $status, $reason, $prepDone]) {
            $participant = $initiative->participants()->create([
                'name' => $name,
                'role' => $role,
                'status' => $status,
                'invite_reason' => $reason,
                'scheduled_at' => $status === 'scheduled' ? now()->addDays(3)->setTime(14, 0) : null,
            ]);

            $participant->segmentValues()->sync(array_values(array_filter([
                $area ? $areas[$area] : null,
                ...array_map(fn (?string $value) => $value ? $values[$value] : null, $groupValues),
            ])));
            $participant->completedChecklistItems()->sync(array_slice($checklist, 0, $prepDone));
        }

        $initiative->messageTemplates()->createMany([
            [
                'name' => 'Email invite',
                'channel' => 'email',
                'subject' => '30 minutes on what slows you down?',
                'body' => "Hi {{first_name}},\n\nWe're looking at where developers at Larkspur lose time, so we can fix the right things first. I'd like to hear your view because {{reason}}.\n\nIt's an informal 30-minute conversation about your recent work and the tools around it. There are no right answers, and nothing you say will be attributed to you by name.\n\nWould one day next week work? Send me a couple of times and I'll set it up.\n\nThanks,\n{{facilitator}}\n{{initiative}}",
                'position' => 0,
            ],
            [
                'name' => 'Chat invite',
                'channel' => 'chat',
                'subject' => null,
                'body' => "Hey {{first_name}}, I'm running short chats about developer friction for {{initiative}}. Would you have 30 minutes next week? I'm asking you because {{reason}}. Anything you share stays anonymous. — {{facilitator}}",
                'position' => 1,
            ],
        ]);
    }
}
