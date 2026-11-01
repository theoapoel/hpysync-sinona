<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    private const GITHUB_REPO   = 'hpySolution/hpy-pos';
    private const GITHUB_BRANCH = 'main';

    public function index()
    {
        return view('update.index', ['local' => $this->localVersionInfo()]);
    }

    public function checkLatest(Request $request)
    {
        $request->validate(['github_token' => 'required|string']);

        try {
            $client = new Client(['timeout' => 10, 'verify' => false]);
            $res    = $client->get(
                'https://api.github.com/repos/' . self::GITHUB_REPO . '/commits/' . self::GITHUB_BRANCH,
                [
                    'headers' => [
                        'User-Agent'    => 'HPYSync-POS/1.0',
                        'Accept'        => 'application/vnd.github.v3+json',
                        'Authorization' => 'Bearer ' . $request->github_token,
                    ],
                ]
            );

            $data = json_decode($res->getBody()->getContents(), true);

            return response()->json([
                'success' => true,
                'sha'     => substr($data['sha'] ?? '', 0, 7),
                'message' => $data['commit']['message'] ?? '',
                'date'    => $data['commit']['author']['date'] ?? '',
                'author'  => $data['commit']['author']['name'] ?? '',
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status === 404) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Token tidak memiliki akses ke repository (404). Pastikan token bertipe Classic PAT dengan scope "repo", atau Fine-grained PAT dengan permission "Contents: Read".',
                ], 422);
            }
            if ($status === 401) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Token tidak valid atau sudah kedaluwarsa (401). Buat token baru di GitHub → Settings → Developer settings → Personal access tokens.',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'error'   => 'GitHub API error ' . $status . ': ' . $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Gagal menghubungi GitHub: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function findGit(): string
    {
        // Cek apakah 'git' tersedia di PATH proses Apache saat ini
        exec('git --version 2>&1', $out, $code);
        if ($code === 0) return 'git';

        // Fallback: lokasi instalasi umum Git for Windows
        $candidates = [
            'C:\\Program Files\\Git\\cmd\\git.exe',
            'C:\\Program Files\\Git\\bin\\git.exe',
            'C:\\Program Files (x86)\\Git\\cmd\\git.exe',
            'C:\\Program Files (x86)\\Git\\bin\\git.exe',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) return '"' . $path . '"';
        }

        return 'git';
    }

    public function run(Request $request)
    {
        $request->validate([
            'github_token' => 'required|string',
        ]);

        $log     = [];
        $base    = base_path();
        $safeDir = str_replace('\\', '/', $base);
        $pullUrl = 'https://' . $request->github_token . '@github.com/' . self::GITHUB_REPO . '.git';
        $git     = $this->findGit();

        // Tulis safe.directory ke global git config — lebih reliable dari flag -c
        // karena git spawn subprocess (remote helper) yang tidak mewarisi -c flag.
        exec($git . ' config --global --add safe.directory ' . escapeshellarg($safeDir) . ' 2>&1');
        // Tambahkan juga versi backslash (Windows path) sebagai fallback
        exec($git . ' config --global --add safe.directory ' . escapeshellarg($base) . ' 2>&1');

        $log[] = '▶ git pull ' . self::GITHUB_BRANCH;
        exec(
            $git . ' -C ' . escapeshellarg($base)
            . ' -c safe.directory=' . escapeshellarg($safeDir)
            . ' pull ' . escapeshellarg($pullUrl) . ' ' . self::GITHUB_BRANCH . ' 2>&1',
            $pullOut,
            $code
        );
        foreach ($pullOut as $line) $log[] = $line;

        if ($code !== 0) {
            return response()->json(['success' => false, 'error' => 'git pull gagal.', 'log' => $log]);
        }

        $log[] = '';
        $log[] = '▶ php artisan migrate --force';
        exec(PHP_BINARY . ' ' . escapeshellarg($base . '/artisan') . ' migrate --force 2>&1', $migrateOut);
        foreach ($migrateOut as $line) $log[] = $line;

        $log[] = '';
        $log[] = '▶ cache:clear / config:clear / view:clear / route:clear';
        foreach (['cache:clear', 'config:clear', 'view:clear', 'route:clear'] as $cmd) {
            $out = [];
            exec(PHP_BINARY . ' ' . escapeshellarg($base . '/artisan') . ' ' . $cmd . ' 2>&1', $out);
            foreach ($out as $line) $log[] = $line;
        }

        return response()->json(['success' => true, 'log' => $log]);
    }

    private function localVersionInfo(): array
    {
        $base     = base_path();
        $headFile = $base . '/.git/HEAD';

        if (!file_exists($headFile)) {
            return ['sha' => 'unknown', 'date' => '', 'message' => '', 'branch' => ''];
        }

        $head   = trim(file_get_contents($headFile));
        $sha    = 'unknown';
        $branch = '';

        if (str_starts_with($head, 'ref: ')) {
            $ref     = substr($head, 5);
            $branch  = basename($ref);
            $refFile = $base . '/.git/' . $ref;
            if (file_exists($refFile)) {
                $sha = trim(file_get_contents($refFile));
            }
        } else {
            $sha = $head;
        }

        $logOut  = [];
        $safeDir = str_replace('\\', '/', $base);
        $git     = $this->findGit();
        exec($git . ' -C ' . escapeshellarg($base) . ' -c safe.directory=' . escapeshellarg($safeDir) . ' log -1 --format="%ci|||%s" HEAD 2>&1', $logOut);
        $date = $message = '';
        if (!empty($logOut[0]) && str_contains($logOut[0], '|||')) {
            [$date, $message] = explode('|||', $logOut[0], 2);
        }

        return [
            'sha'     => substr($sha, 0, 7),
            'date'    => trim($date),
            'message' => trim($message),
            'branch'  => $branch,
        ];
    }
}
