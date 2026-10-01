<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DictionaryController extends Controller
{
    public function define(Request $request)
    {
        $word = trim(preg_replace("/[^a-z']/", '', strtolower((string) $request->query('word', ''))), "'");
        if ($word === '') {
            return response()->json(['error' => 'word is required'], 422);
        }

        // 1. Curated/local definitions muna (pwede mong i-edit para sa mga bata)
        $local = DB::table('word_definitions')->where('word', $word)->first();
        if ($local) {
            return response()->json([
                'word' => $word,
                'part_of_speech' => $local->part_of_speech,
                'definition' => $local->definition,
                'source' => 'local',
            ]);
        }

        // 2. Fallback sa dictionaryapi.dev, naka-cache ng 30 araw
        $found = Cache::remember("define:$word", now()->addDays(30), function () use ($word) {
            foreach ($this->candidates($word) as $candidate) {
                try {
                    $res = Http::timeout(8)->get(
                        'https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($candidate)
                    );
                } catch (\Throwable $e) {
                    continue;
                }
                if (!$res->ok()) continue;

                foreach ($res->json() ?? [] as $entry) {
                    foreach ($entry['meanings'] ?? [] as $m) {
                        $def = $m['definitions'][0]['definition'] ?? null;
                        if ($def) {
                            return ['part_of_speech' => $m['partOfSpeech'] ?? '', 'definition' => $def];
                        }
                    }
                }
            }
            return null; // hindi nacache ang null, kaya makakapag-retry
        });

        if (!$found) {
            return response()->json(['error' => 'No definition found'], 404);
        }

        return response()->json(['word' => $word, 'source' => 'dictionary'] + $found);
    }

    private function candidates(string $word): array
    {
        $base = preg_replace("/'s$/", '', $word);
        $out = [$word, $base];
        foreach (['es', 's', 'ed', 'ing', 'ly'] as $suf) {
            if (strlen($base) > strlen($suf) + 2 && str_ends_with($base, $suf)) {
                $out[] = substr($base, 0, -strlen($suf));
            }
        }
        return array_values(array_unique(array_filter($out)));
    }
}   