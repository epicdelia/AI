<?php

namespace MagicAI\Updater\Controllers;

use App\Helpers\Classes\InstallationHelper;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use MagicAI\Updater\Facades\Updater;

class UpdaterController
{
    public function index(Request $request): View
    {
        return view('magicai-updater::index', [
            'permission' => true,
            'data'       => Updater::checker(),
            'user'       => $request->user(),
        ]);
    }

    public function check(): array
    {
        return Updater::checker();
    }

    public function update(Request $request): RedirectResponse
    {
        Updater::downloadNewUpdater();

        return back()->with([
            'message' => trans('The updater has been successfully downloaded.'),
            'type'    => 'success',
        ]);
    }

    public function backup(Request $request): Factory|Application|View|\Illuminate\View\View|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $data = Updater::backupView();

        if ($data['updated']) {
            return redirect()->route('updater.index')->with([
                'message' => 'MagicAI is already up to date.',
                'type'    => 'success',
            ]);
        }

        if ($request->isMethod('get') && ! Updater::isLastBackupRecent()) {
            return redirect()->route('updater.index')->with([
                'message' => trans('The backup file could not be found. Please try again.'),
                'type'    => 'success',
            ]);
        }

        if ($request->isMethod('post') && ! Updater::isLastBackupRecent()) {
            Updater::backup();
        }

        return view('magicai-updater::index', [
            'permission' => true,
            'data'       => Updater::backupView(),
            'fileName'   => Updater::findLastBackup(),
        ]);
    }

    public function upgrade(Request $request): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {
        $backupFileName = Updater::findLastBackup();

        if (file_exists(base_path($backupFileName))) {

            Updater::updateNewVersion($backupFileName);

            return response([
                'message' => trans('Upgrade completed successfully.'),
                'type'    => 'success',
            ], 200);
        }

        return response([
            'message' => 'The backup file could not be found. Please try again.',
            'type'    => 'success',
        ], 422);
    }

    public function downloadStep(): Factory|Application|View|\Illuminate\View\View|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $data = Updater::downloadView();

        $backupFileName = Updater::findLastBackup();

        if (! file_exists(base_path($backupFileName))) {
            return redirect()->route('updater.index')->with([
                'message' => 'The backup file could not be found. Please try again.',
                'type'    => 'error',
            ]);
        }

        if ($data['updated']) {
            return redirect()->route('updater.index')->with([
                'message' => 'MagicAI is already up to date.',
                'type'    => 'success',
            ]);
        }

        if (! $data['isDownload']) {
            Updater::downloadNewVersion();
        }

        return view('magicai-updater::index', [
            'permission' => true,
            'data'       => $data,
            'fileName'   => 'new-version' . Updater::getDownloadVersion() . '.zip',
        ]);
    }

    public function download(Request $request): RedirectResponse
    {
        $backupFileName = Updater::findLastBackup();

        if (file_exists(base_path($backupFileName))) {

            Updater::downloadNewVersion();

            return to_route('updater.download-step')->with([
                'type'    => 'success',
                'message' => 'The download file has been successfully downloaded.',
            ]);
        }

        throw ValidationException::withMessages([
            'message' => 'The backup file could not be found. Please try again.',
        ]);
    }

    public function versionCheck(): JsonResponse
    {
        $versionCheck = Updater::versionCheck();

        if ($versionCheck) {
            try {

                Artisan::call('migrate', ['--force' => true]);

                InstallationHelper::runInstallation();

                Artisan::call('optimize:clear');
            } catch (Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'updated' => $versionCheck,
                ]);
            }
        }

        return response()->json([
            'message' => $versionCheck ? 'MagicAI is already up to date.' : 'MagicAI don\'t updated.',
            'updated' => $versionCheck,
        ]);
    }

    public function forPanel(): JsonResponse
    {
        return response()->json(Updater::forPanel());
    }
}
