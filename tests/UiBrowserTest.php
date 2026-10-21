<?php
use PHPUnit\Framework\TestCase;

/** Optional native Chromium check. No npm, browser driver or runtime dependency. */
final class UiBrowserTest extends TestCase {
    public function testResponsiveComponentsFocusAndEmbeddedIsolation(): void {
        $browser = getenv('PLOGGER_TEST_BROWSER');
        if (!$browser || !is_file($browser)) self::markTestSkipped('Set PLOGGER_TEST_BROWSER to a native Chrome/Chromium/Edge executable.');
        $root = sys_get_temp_dir().'/plog-ui-browser-'.bin2hex(random_bytes(8));
        mkdir($root);
        try {
            foreach (['gallery','admin'] as $area) {
                $links = '';
                foreach (['tokens','base','layout','components',$area] as $name) {
                    $path = str_replace('\\','/',dirname(__DIR__).'/plog-content/ui/css/'.$name.'.css');
                    $url = 'file:///'.ltrim($path, '/');
                    if (PHP_OS_FAMILY !== 'Windows') $url = 'file://'.$path;
                    $links .= '<link rel="stylesheet" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">';
                }
                $html = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$links
                    .'<p id="host">Embedding host</p><section class="plog-ui plog-'.$area.'"><div class="plog-shell">'
                    .'<a class="plog-skip" href="#plog-main">Skip to content</a><div id="plog-main" tabindex="-1">'
                    .'<h1>Photograph gallery</h1><nav class="plog-nav"><a href="#one">Collection</a><a href="#two">Album</a></nav>'
                    .'<ul class="plog-gallery-grid"><li class="plog-card"><div class="plog-card-body"><h2>First photograph</h2></div></li>'
                    .'<li class="plog-card"><div class="plog-card-body"><h2>Second photograph</h2></div></li></ul>'
                    .'<form><label for="caption">Caption</label><input id="caption"><label for="description">Description</label>'
                    .'<textarea id="description">Multiline description</textarea><button type="button">Save</button></form>'
                    .'<div class="plog-table-scroll" role="region" aria-label="Photographs" tabindex="0"><table style="min-width:900px">'
                    .'<tr><th scope="col">Photograph</th><th scope="col">Description</th></tr><tr><td>Fixture</td><td>Text</td></tr></table></div>'
                    .'</div></div></section><output id="plog-test-result"></output><script>'
                    .'window.addEventListener("load",()=>{const skip=document.querySelector(".plog-skip");skip.focus();'
                    .'const outline=getComputedStyle(skip).outlineStyle;skip.click();'
                    .'const result=JSON.stringify({'
                    .'width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1,'
                    .'outline,focus:document.activeElement.id,hostColor:getComputedStyle(document.getElementById("host")).color,'
                    .'grid:getComputedStyle(document.querySelector(".plog-gallery-grid")).display,'
                    .'columns:getComputedStyle(document.querySelector(".plog-gallery-grid")).gridTemplateColumns.split(" ").length,'
                    .'reduced:matchMedia("(prefers-reduced-motion: reduce)").matches});'
                    .'document.getElementById("plog-test-result").textContent=result;parent.postMessage(result,"*");});</script>';
                file_put_contents($root.'/fixture.html', $html);
                foreach ([360,768,1280] as $width) {
                    // A fixed iframe supplies the exact CSS viewport even when a desktop OS
                    // clamps the headless browser window to its minimum window size.
                    file_put_contents($root.'/viewport.html', '<!doctype html><script>addEventListener("message",e=>{document.getElementById("plog-test-result").textContent=e.data;});</script>'
                        .'<iframe title="UI fixture" src="fixture.html" style="border:0;width:'.$width.'px;height:900px"></iframe><output id="plog-test-result"></output>');
                    $profile = $root.'/profile-'.$area.'-'.$width;
                    $path = str_replace('\\','/',$root.'/viewport.html');
                    $url = PHP_OS_FAMILY === 'Windows' ? 'file:///'.ltrim($path,'/') : 'file://'.$path;
                    $args = [$browser,'--headless','--disable-gpu','--no-first-run','--no-default-browser-check',
                        '--allow-file-access-from-files','--force-prefers-reduced-motion','--user-data-dir='.$profile,
                        '--window-size=1400,1000','--virtual-time-budget=1000','--dump-dom',$url];
                    $process = proc_open($args, [0=>['pipe','r'],1=>['file',$root.'/stdout','w'],2=>['file',$root.'/stderr','w']], $pipes);
                    self::assertIsResource($process); fclose($pipes[0]);
                    $start = microtime(true);
                    while (proc_get_status($process)['running'] && microtime(true)-$start < 25) usleep(100000);
                    if (proc_get_status($process)['running']) { proc_terminate($process); self::fail('Native browser timed out.'); }
                    proc_close($process);
                    $output = file_get_contents($root.'/stdout');
                    self::assertNotSame('', $output, file_get_contents($root.'/stderr'));
                    $dom = new DOMDocument(); @$dom->loadHTML($output);
                    $node = (new DOMXPath($dom))->query('//*[@id="plog-test-result"]')->item(0);
                    self::assertNotNull($node, file_get_contents($root.'/stderr'));
                    $report = json_decode($node->textContent, true, 512, JSON_THROW_ON_ERROR);
                    self::assertSame($width, $report['width']);
                    self::assertFalse($report['overflow'], $area.' at '.$width.'px');
                    self::assertSame('solid', $report['outline']);
                    self::assertSame('plog-main', $report['focus']);
                    self::assertSame('rgb(0, 0, 0)', $report['hostColor']);
                    self::assertTrue($report['reduced']);
                    if ($area === 'gallery') {
                        self::assertSame('grid', $report['grid']);
                        if ($width === 360) self::assertSame(1, $report['columns']);
                        else self::assertGreaterThanOrEqual(2, $report['columns']);
                    }
                }
            }
        } finally {
            // Only the random fixture directory created above is removed.
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                if ($file->isDir() && !$file->isLink()) @rmdir($file->getPathname()); else @unlink($file->getPathname());
            }
            @rmdir($root);
        }
    }
}
