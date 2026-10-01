<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DictionaryController extends Controller
{
    private array $trace = [];

    public function define(Request $request)
    {
        $word = trim(preg_replace("/[^a-z']/", '', strtolower((string) $request->query('word', ''))), "'");
        if ($word === '') {
            return response()->json(['error' => 'word is required'], 422);
        }
        $debug = $request->boolean('debug');

        // 1. Optional na curated override (kung may table at may laman)
        if (Schema::hasTable('word_definitions')) {
            $local = DB::table('word_definitions')->where('word', $word)->first();
            if ($local) {
                return response()->json([
                    'word' => $word,
                    'part_of_speech' => $local->part_of_speech,
                    'definition' => $local->definition,
                    'source' => 'local',
                ]);
            }
        }

        // 2. Direktang search sa online dictionaries, naka-cache ng 30 araw
        $found = Cache::get("define:$word");
        if (!$found) {
            $found = $this->lookupOnline($word);
            if ($found) {
                Cache::put("define:$word", $found, now()->addDays(30));
            }
        }

        if (!$found) {
            $body = ['error' => 'No definition found'];
            if ($debug) $body['trace'] = $this->trace;
            return response()->json($body, 404);
        }

        $body = ['word' => $word] + $found;
        if ($debug) $body['trace'] = $this->trace;
        return response()->json($body);
    }

    private function lookupOnline(string $word): ?array
    {
        foreach ($this->candidates($word) as $candidate) {
            foreach (['fromDictionaryApi', 'fromDatamuse', 'fromWiktionary'] as $provider) {
                try {
                    $res = $this->$provider($candidate);
                } catch (\Throwable $e) {
                    $this->trace[] = "$provider($candidate): exception " . $e->getMessage();
                    Log::warning("define $provider($candidate): " . $e->getMessage());
                    continue;
                }
                if ($res) {
                    $this->trace[] = "$provider($candidate): OK";
                    return $res + ['source' => $provider];
                }
                $this->trace[] = "$provider($candidate): no result";
            }
        }
        return null;
    }

    private function http()
    {
        return Http::timeout(8)->withHeaders([
            'User-Agent' => 'ReadSmartApp/1.0 (reading app for kids)',
            'Accept' => 'application/json',
        ]);
    }

    // Provider 1: dictionaryapi.dev
    private function fromDictionaryApi(string $w): ?array
    {
        $res = $this->http()->get('https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($w));
        if (!$res->ok()) {
            $this->trace[] = "dictionaryapi($w): HTTP " . $res->status();
            return null;
        }
        foreach ($res->json() ?? [] as $entry) {
            foreach ($entry['meanings'] ?? [] as $m) {
                $def = $m['definitions'][0]['definition'] ?? null;
                if ($def) {
                    return ['part_of_speech' => $m['partOfSpeech'] ?? '', 'definition' => $def];
                }
            }
        }
        return null;
    }

    // Provider 2: Datamuse (libre, walang API key)
    private function fromDatamuse(string $w): ?array
    {
        $res = $this->http()->get('https://api.datamuse.com/words', ['sp' => $w, 'md' => 'd', 'max' => 1]);
        if (!$res->ok()) {
            $this->trace[] = "datamuse($w): HTTP " . $res->status();
            return null;
        }
        $first = ($res->json() ?? [])[0] ?? null;
        if (!$first || strtolower($first['word'] ?? '') !== $w || empty($first['defs'])) {
            return null;
        }
        [$pos, $def] = array_pad(explode("\t", $first['defs'][0], 2), 2, '');
        $posMap = ['n' => 'noun', 'v' => 'verb', 'adj' => 'adjective', 'adv' => 'adverb', 'u' => ''];
        return $def === '' ? null : [
            'part_of_speech' => $posMap[$pos] ?? $pos,
            'definition' => ucfirst($def) . '.',
        ];
    }

    // Provider 3: Wiktionary
    private function fromWiktionary(string $w): ?array
    {
        $res = $this->http()->get('https://en.wiktionary.org/api/rest_v1/page/definition/' . rawurlencode($w));
        if (!$res->ok()) {
            $this->trace[] = "wiktionary($w): HTTP " . $res->status();
            return null;
        }
        foreach (($res->json()['en'] ?? []) as $block) {
            foreach ($block['definitions'] ?? [] as $d) {
                $text = trim(html_entity_decode(strip_tags($d['definition'] ?? '')));
                if ($text !== '') {
                    return ['part_of_speech' => strtolower($block['partOfSpeech'] ?? ''), 'definition' => $text];
                }
            }
        }
        return null;
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