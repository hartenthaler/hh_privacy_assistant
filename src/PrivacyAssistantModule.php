<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\PrivacyAssistant;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\RequestHandlers\SiteRegistrationPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Localization\Translation;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_key_first;
use function date;
use function file_exists;
use function floor;
use function in_array;
use function is_array;
use function max;
use function min;
use function preg_match;
use function preg_replace;
use function route;
use function strip_tags;
use function strrpos;
use function strtok;
use function substr;
use function time;
use function usort;

final class PrivacyAssistantModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    private const MODULE_TITLE = 'Privacy and Security Assistant';
    private const VERSION = '2.2.6.0';
    private const LATEST_VERSION_URL = 'https://raw.githubusercontent.com/hartenthaler/hh_privacy_assistant/main/latest-version.txt';
    private const SUPPORT_URL = 'https://github.com/hartenthaler/hh_privacy_assistant';

    private const LEGAL_NOTICE_MODULE = '_hh_legal_notice_';
    private const LEGAL_NOTICE_RETENTION_SETTING = 'inactiveUserYears';
    private const PREF_LAST_SCAN = 'lastScan';
    private const PREF_LAST_COUNT = 'lastExpiredCount';
    private const PREF_LAST_YEARS = 'lastRetentionYears';
    private const SCAN_INTERVAL_SECONDS = 86400;
    private const DEFAULT_RELEASE_YEARS = 30;
    private const PROTECTION_RESTRICTION = 'CONFIDENTIAL';
    private const SENSITIVE_FACT_TAGS = ['BAPM', 'CHR', 'CONF', 'DEAT', 'DSCR', 'EVEN', 'FACT', 'RELI'];
    private const SENSITIVE_PATTERNS = [
        [
            'label' => 'Ethnic origin',
            'tags' => ['FACT'],
            'type' => 'Ethnic Origin',
        ],
        [
            'label' => 'Physical description',
            'tags' => ['DSCR'],
        ],
        [
            'label' => 'Political party membership',
            'tags' => ['FACT'],
            'type' => 'Political Party Membership',
        ],
        [
            'label' => 'Political affiliation',
            'tags' => ['EVEN', 'FACT'],
            'type' => 'Political Affiliation',
        ],
        [
            'label' => 'Religious affiliation',
            'tags' => ['RELI'],
        ],
        [
            'label' => 'Religious event',
            'tags' => ['EVEN'],
            'type' => 'Religious Event',
        ],
        [
            'label' => 'Baptism or church ceremony',
            'tags' => ['BAPM', 'CHR', 'CONF'],
        ],
        [
            'label' => 'Trade union membership',
            'tags' => ['FACT'],
            'type' => 'Trade Union Membership',
        ],
        [
            'label' => 'Y-DNA haplogroup',
            'tags' => ['FACT'],
            'type' => 'Y-DNA Haplogroup',
        ],
        [
            'label' => 'mtDNA haplogroup',
            'tags' => ['FACT'],
            'type' => 'mtDNA Haplogroup',
        ],
        [
            'label' => 'DNA test',
            'tags' => ['EVEN'],
            'type' => 'DNA Test',
        ],
        [
            'label' => 'Cause of death',
            'tags' => ['DEAT'],
            'attribute' => 'CAUS',
        ],
    ];

    private UserService $userService;
    private TreeService $treeService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->treeService = Registry::container()->get(TreeService::class);
    }

    public function title(): string
    {
        return I18N::translate(self::MODULE_TITLE);
    }

    public function description(): string
    {
        return I18N::translate('Monitors privacy and security tasks for this webtrees site.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Hermann Hartenthaler';
    }

    public function customModuleVersion(): string
    {
        return self::VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return self::LATEST_VERSION_URL;
    }

    public function customModuleSupportUrl(): string
    {
        return self::SUPPORT_URL;
    }

    public function customTranslations(string $language): array
    {
        $lang_dir = $this->resourcesFolder() . 'lang' . DIRECTORY_SEPARATOR;
        $file = $lang_dir . $language . '.mo';

        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        }

        $file = $lang_dir . $language . '.php';

        if (file_exists($file)) {
            $translations = include $file;

            return is_array($translations) ? $translations : [];
        }

        return [];
    }

    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views' . DIRECTORY_SEPARATOR);
    }

    public function bodyContent(): string
    {
        $this->runScheduledRetentionScan();

        return '';
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $this->layout = Webtrees::LAYOUT_ADMINISTRATION;
        $this->runRetentionScan();

        return $this->adminResponse($request);
    }

    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!Auth::isAdmin()) {
            throw new HttpAccessDeniedException();
        }

        $this->layout = Webtrees::LAYOUT_ADMINISTRATION;

        return $this->adminResponse($request, true);
    }

    private function adminResponse(ServerRequestInterface $request, bool $submitted = false): ResponseInterface
    {
        $params = $submitted ? (array) $request->getParsedBody() : $request->getQueryParams();
        $trees = $this->treeOptions();
        $selected_tree_name = (string) ($params['protectionTree'] ?? array_key_first($trees) ?? '');
        $release_years = $this->validReleaseYears((int) ($params['releaseYears'] ?? self::DEFAULT_RELEASE_YEARS));
        $protection_result = [];
        $applied = false;

        if ($submitted && $selected_tree_name !== '') {
            $apply = (string) ($params['task'] ?? '') === 'apply';
            $tree = $this->treeByName($selected_tree_name);

            if ($tree instanceof Tree) {
                $protection_result = $this->reviewSensitiveFacts($tree, $release_years, $apply);
                $applied = $apply;
            }
        }

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'description' => $this->description(),
            'retentionYears' => $this->inactiveUserRetentionYears(),
            'lastScan' => $this->formattedTimestamp((int) $this->getPreference(self::PREF_LAST_SCAN, '0')),
            'lastExpiredCount' => (int) $this->getPreference(self::PREF_LAST_COUNT, '0'),
            'lastRetentionYears' => (int) $this->getPreference(self::PREF_LAST_YEARS, '0'),
            'expiredAccounts' => $this->expiredUserAccounts(),
            'legalNoticeConfigLink' => route('module', ['module' => self::LEGAL_NOTICE_MODULE, 'action' => 'Admin']),
            'registrationConsentStatus' => $this->registrationConsentStatus(),
            'siteRegistrationConfigLink' => route(SiteRegistrationPage::class),
            'treeOptions' => $trees,
            'selectedProtectionTree' => $selected_tree_name,
            'releaseYears' => $release_years,
            'protectionResult' => $protection_result,
            'protectionApplied' => $applied,
            'protectionSubmitted' => $submitted,
        ]);
    }

    private function runScheduledRetentionScan(): void
    {
        try {
            $last_scan = (int) $this->getPreference(self::PREF_LAST_SCAN, '0');

            if ($last_scan + self::SCAN_INTERVAL_SECONDS <= time()) {
                $this->runRetentionScan();
            }
        } catch (Throwable) {
            // Global page rendering must not fail because of an assistant scan.
        }
    }

    private function runRetentionScan(): void
    {
        $years = $this->inactiveUserRetentionYears();
        $expired_accounts = $years > 0 ? $this->expiredUserAccounts() : [];

        $this->setPreference(self::PREF_LAST_SCAN, (string) time());
        $this->setPreference(self::PREF_LAST_COUNT, (string) count($expired_accounts));
        $this->setPreference(self::PREF_LAST_YEARS, (string) $years);
    }

    private function inactiveUserRetentionYears(): int
    {
        $value = DB::table('module_setting')
            ->where('setting_name', '=', self::LEGAL_NOTICE_RETENTION_SETTING)
            ->where(function ($query): void {
                $query
                    ->where('module_name', '=', self::LEGAL_NOTICE_MODULE)
                    ->orWhere('module_name', 'like', '%legal_notice%');
            })
            ->orderByRaw('module_name = ? desc', [self::LEGAL_NOTICE_MODULE])
            ->value('setting_value');

        $years = (int) ($value ?? 0);

        return max(0, min(10, $years));
    }

    /**
     * @return array{registrationEnabled:bool,cautionEnabled:bool,perUserConsentStored:bool}
     */
    private function registrationConsentStatus(): array
    {
        return [
            'registrationEnabled' => Site::getPreference('USE_REGISTRATION_MODULE') === '1',
            'cautionEnabled' => Site::getPreference('SHOW_REGISTER_CAUTION') === '1',
            'perUserConsentStored' => false,
        ];
    }

    /**
     * @return list<array{id:int,userName:string,realName:string,email:string,registered:string,lastActivity:string,inactiveDays:int,isAdmin:bool}>
     */
    private function expiredUserAccounts(): array
    {
        $years = $this->inactiveUserRetentionYears();

        if ($years === 0) {
            return [];
        }

        $cutoff = strtotime('-' . $years . ' years');
        $now = time();

        $accounts = [];

        foreach ($this->userService->all() as $user) {
            $last_activity = $this->lastActivityTimestamp($user);

            if ($last_activity === 0 || $last_activity > $cutoff) {
                continue;
            }

            $accounts[] = [
                'id' => $user->id(),
                'userName' => $user->userName(),
                'realName' => $user->realName(),
                'email' => $user->email(),
                'registered' => $this->formattedTimestamp((int) $user->getPreference(UserInterface::PREF_TIMESTAMP_REGISTERED)),
                'lastActivity' => $this->formattedTimestamp($last_activity),
                'inactiveDays' => (int) floor(($now - $last_activity) / 86400),
                'isAdmin' => $user->getPreference(UserInterface::PREF_IS_ADMINISTRATOR) === '1',
            ];
        }

        usort($accounts, static fn (array $a, array $b): int => $b['inactiveDays'] <=> $a['inactiveDays']);

        return $accounts;
    }

    private function lastActivityTimestamp(UserInterface $user): int
    {
        $last_activity = (int) $user->getPreference(UserInterface::PREF_TIMESTAMP_ACTIVE);

        if ($last_activity > 0) {
            return $last_activity;
        }

        return (int) $user->getPreference(UserInterface::PREF_TIMESTAMP_REGISTERED);
    }

    private function formattedTimestamp(int $timestamp): string
    {
        if ($timestamp === 0) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return array<string,string>
     */
    private function treeOptions(): array
    {
        $trees = [];

        foreach ($this->treeService->all() as $tree) {
            $trees[$tree->name()] = $tree->title();
        }

        return $trees;
    }

    private function treeByName(string $tree_name): Tree|null
    {
        return $this->treeService->all()->get($tree_name);
    }

    private function validReleaseYears(int $years): int
    {
        return max(0, min(150, $years));
    }

    /**
     * @return list<array{xref:string,name:string,url:string,lifespan:string,pattern:string,fact:string,deathStatus:string,action:string,changed:bool}>
     */
    private function reviewSensitiveFacts(Tree $tree, int $release_years, bool $apply): array
    {
        $query = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->where(function ($query): void {
                foreach (self::SENSITIVE_FACT_TAGS as $tag) {
                    $query->orWhere('i_gedcom', 'like', "%\n1 " . $tag . "%");
                }
            })
            ->select(['i_id'])
            ->orderBy('i_id');

        $rows = $query->get();

        $result = [];

        foreach ($rows as $row) {
            $individual = Registry::individualFactory()->make((string) $row->i_id, $tree);

            if (!$individual instanceof Individual) {
                continue;
            }

            $dead_long_enough = $this->deadLongerThan($individual, $release_years);
            $death_status = $dead_long_enough
                ? I18N::plural('dead for at least %s year', 'dead for at least %s years', $release_years, I18N::number($release_years))
                : I18N::plural('not known to be dead for at least %s year', 'not known to be dead for at least %s years', $release_years, I18N::number($release_years));

            foreach ($individual->facts(self::SENSITIVE_FACT_TAGS, false, Auth::PRIV_HIDE, true) as $fact) {
                $pattern = $this->sensitivePatternLabel($fact);

                if ($pattern === '') {
                    continue;
                }

                $gedcom = $fact->gedcom();
                $has_confidential = $this->hasConfidentialRestriction($gedcom);
                $new_gedcom = $gedcom;
                $action = 'unchanged';

                if ($dead_long_enough && $has_confidential) {
                    $new_gedcom = $this->removeConfidentialRestriction($gedcom);
                    $action = 'remove';
                } elseif (!$dead_long_enough && !$has_confidential) {
                    $new_gedcom = $this->addConfidentialRestriction($gedcom);
                    $action = 'add';
                }

                if ($apply && $new_gedcom !== $gedcom) {
                    $individual->updateFact($fact->id(), $new_gedcom, true);
                }

                $result[] = [
                    'xref' => $individual->xref(),
                    'name' => $individual->fullName(),
                    'url' => $individual->url(),
                    'lifespan' => strip_tags($individual->lifespan()),
                    'pattern' => $pattern,
                    'fact' => $this->firstGedcomLine($gedcom),
                    'deathStatus' => $death_status,
                    'action' => $action,
                    'changed' => $new_gedcom !== $gedcom,
                ];
            }
        }

        return $result;
    }

    private function sensitivePatternLabel(object $fact): string
    {
        $tag = $this->factTag($fact);

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (!in_array($tag, $pattern['tags'], true)) {
                continue;
            }

            if (isset($pattern['type']) && $fact->attribute('TYPE') !== $pattern['type']) {
                continue;
            }

            if (isset($pattern['attribute']) && $fact->attribute($pattern['attribute']) === '') {
                continue;
            }

            return I18N::translate($pattern['label']);
        }

        return '';
    }

    private function factTag(object $fact): string
    {
        $tag = $fact->tag();
        $position = strrpos($tag, ':');

        return $position === false ? $tag : substr($tag, $position + 1);
    }

    private function deadLongerThan(Individual $individual, int $years): bool
    {
        $death_date = $individual->getEstimatedDeathDate();

        return $death_date->isOK()
            && $death_date->addYears($years)->maximumJulianDay() <= Registry::timestampFactory()->now()->julianDay();
    }

    private function hasConfidentialRestriction(string $gedcom): bool
    {
        return preg_match('/\n2 RESN ' . self::PROTECTION_RESTRICTION . '(?:\n|$)/', $gedcom) === 1;
    }

    private function addConfidentialRestriction(string $gedcom): string
    {
        return $gedcom . "\n2 RESN " . self::PROTECTION_RESTRICTION;
    }

    private function removeConfidentialRestriction(string $gedcom): string
    {
        return preg_replace('/\n2 RESN ' . self::PROTECTION_RESTRICTION . '(?=\n|$)/', '', $gedcom) ?? $gedcom;
    }

    private function firstGedcomLine(string $gedcom): string
    {
        return strtok($gedcom, "\n") ?: $gedcom;
    }
}
