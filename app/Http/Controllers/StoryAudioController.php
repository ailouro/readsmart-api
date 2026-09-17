<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryPage;
use App\Services\GoogleService; // ⬅️ naka-import na ngayon

class StoryAudioController extends Controller
{
    /**
     * Hakbang A: Kukunin ang text, gagawing MP3 gamit ang Google, at ia-upload sa Cloudinary.
     */
    public function generateTts(Request $request, $storyId, $slideIndex)
    {
        $request->validate([
            'text' => 'required|string',
            'script_index' => 'required|integer',
            'lang' => 'nullable|string'
        ]);

        $text = $request->input('text');
        $scriptIndex = $request->input('script_index');
        $lang = $request->input('lang', 'en');

        $googleService = new GoogleService();
        $result = $googleService->translate($storyId, $slideIndex, $scriptIndex, $text, $lang);

        // translate() gumagawa ng ['error' => ...] kapag nabigo
        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        // Optional pero recommended: i-save ang Cloudinary URL sa DB para hindi
        // na kailangang i-regenerate paulit-ulit. Kunin ang page gamit ang
        // $slideIndex (0-based, kagaya ng ginagawa sa updateSlideScript).
        //
        // 🛠️ FIX: dati isang column lang (audio_url) ang ginagamit, kaya kung
        // may 2+ audio_scripts ang isang page, ma-overwrite ng bandang huli
        // yung URL ng unang script. Ngayon per-script_index na ang pag-imbak
        // sa audio_urls (JSON map), hindi na isang shared column.
        $story = Story::find($storyId);
        if ($story) {
            $page = $story->pages()->orderBy('id', 'asc')->skip($slideIndex)->first();
            if ($page) {
                $urls = $page->audio_urls ?? [];
                $urls[$scriptIndex] = $result['path'];
                $page->audio_urls = $urls;

                // Panatilihin din ang legacy audio_url kapag ito ang script #0,
                // para hindi masira ang mga lumang bahagi ng app na dumidepende dun.
                if ((int) $scriptIndex === 0) {
                    $page->audio_url = $result['path'];
                }

                $page->save();
            }
        }

        return response()->json([
            'message' => 'AI Voice generated and uploaded successfully!',
            'filename' => $result['filename'],
            'audio_url' => $result['path'],
        ], 200);
    }

    /**
     * Hakbang B: I-redirect papunta sa Cloudinary URL na naka-save na sa DB,
     * imbes na maghanap sa local disk (na nawawala kapag nag-redeploy sa Railway).
     *
     * 🛠️ FIX: dati hindi binabasa ang `script_index` mula sa query string, kaya
     * kung ano man ang huling na-generate na audio_url sa page (ibang script
     * pa man), yun palagi ibinabalik — mali kung ilang audio_scripts ang page.
     * Ngayon required na ang script_index (default 0 para hindi masira ang
     * mga lumang request na wala pa nito) at kinukuha ang tamang URL slot.
     */
    public function getAudio(Request $request)
    {
        $storyId = $request->query('story_id');
        $pageIndex = $request->query('page_index');
        $scriptIndex = (int) $request->query('script_index', 0);

        $story = Story::find($storyId);
        if (!$story) {
            return response()->json(['error' => 'Story not found'], 404);
        }

        $page = $story->pages()->orderBy('id', 'asc')->skip($pageIndex)->first();
        if (!$page) {
            return response()->json(['error' => 'Slide page not found'], 404);
        }

        $audioUrl = $page->audioUrlFor($scriptIndex);
        if (!$audioUrl) {
            return response()->json(['error' => 'Audio file not found. Please generate it first.'], 404);
        }

        return redirect($audioUrl);
    }

    /**
     * Hakbang C: I-save o i-update ang text/script ng isang partikular na page/slide sa DB.
     */
    public function updateSlideScript(Request $request, $storyId, $slideIndex)
    {
        $request->validate([
            'audio_script' => 'required|string'
        ]);

        $newScript = $request->input('audio_script');

        $story = Story::find($storyId);
        if (!$story) {
            return response()->json(['error' => 'Story not found'], 404);
        }

        $page = $story->pages()->orderBy('id', 'asc')->skip($slideIndex)->first();
        if (!$page) {
            return response()->json(['error' => 'Slide page not found at index ' . $slideIndex], 404);
        }

        $page->audio_scripts = [$newScript];
        $page->save();

        return response()->json([
            'message' => 'Slide text updated successfully in DB!',
            'page_id' => $page->id
        ], 200);
    }
}