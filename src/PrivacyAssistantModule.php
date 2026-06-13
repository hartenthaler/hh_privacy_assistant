<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\PrivacyAssistant;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function date;
use function file_exists;
use function floor;
use function is_array;
use function max;
use function route;
use function time;

final class PrivacyAssistantModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    private const MODULE_TITLE = 'Privacy and Security Assistant';
    private const VERSION = '0.1.0';
    private const LATEST_VERSION_URL = 'https://raw.githubusercontent.com/hartenthaler/hh_privacy_assistant/main/latest-version.txt';
    private const SUPPORT_URL = 'https://github.com/hartenthaler/hh_privacy_assistant';

    private const LEGAL_NOTICE_MODULE = '_hh_legal_notice_';
    private const LEGAL_NOTICE_RETENTION_SETTING = 'inactiveUserYears';
    private const PREF_LAST_SCAN = 'lastScan';
    private const PREF_LAST_COUNT = 'lastExpiredCount';
    private const PREF_LAST_YEARS = 'lastRetentionYears';
    private const SCAN_INTERVAL_SECONDS = 86400;

    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
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
        $file = $this->resourcesFolder() . 'lang' . DIRECTORY_SEPARATOR . $language . '.php';

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

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'description' => $this->description(),
            'retentionYears' => $this->inactiveUserRetentionYears(),
            'lastScan' => $this->formattedTimestamp((int) $this->getPreference(self::PREF_LAST_SCAN, '0')),
            'lastExpiredCount' => (int) $this->getPreference(self::PREF_LAST_COUNT, '0'),
            'lastRetentionYears' => (int) $this->getPreference(self::PREF_LAST_YEARS, '0'),
            'expiredAccounts' => $this->expiredUserAccounts(),
            'legalNoticeConfigLink' => route('module', ['module' => self::LEGAL_NOTICE_MODULE, 'action' => 'Admin']),
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
}
