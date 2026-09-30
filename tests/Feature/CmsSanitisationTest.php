<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\CmsContentSanitizer;
use App\Services\CmsPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards that CMS HTML is sanitised, and proves it at the right layer.
 *
 * §58 requires sanitised CMS HTML. The news view renders the body with {!! !!},
 * which is only defensible because the stored value is already clean — so these
 * tests assert on WHAT IS STORED, not only on what the page renders. A test that
 * checked the response would still pass while the payload sat in the database
 * waiting for a different renderer.
 */
class CmsSanitisationTest extends TestCase
{
    use RefreshDatabase;

    private CmsPostService $cms;

    private CmsContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->cms = app(CmsPostService::class);
        $this->sanitizer = app(CmsContentSanitizer::class);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user->refresh();
    }

    /**
     * @test
     */
    public function test_a_script_tag_is_stripped_before_it_is_stored(): void
    {
        $post = $this->cms->create($this->editor(), [
            'title' => 'Berita',
            'body' => '<p>Halo.</p><script>alert(1)</script>',
        ]);

        $stored = (string) Post::findOrFail($post->id)->body;

        // Asserted on the stored value, because that is what every future
        // renderer will read.
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('alert(1)', $stored);
        $this->assertStringContainsString('Halo.', $stored);
    }

    /**
     * @test
     */
    public function test_event_handlers_and_javascript_urls_are_removed(): void
    {
        $payload = '<a href="javascript:alert(1)" onclick="steal()">Klik</a>'
            .'<p onmouseover="x()">Teks</p>';

        $stored = (string) $this->sanitizer->clean($payload);

        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('onmouseover', $stored);
        // The element itself survives, stripped of its handlers.
        $this->assertStringContainsString('Teks', $stored);
    }

    /**
     * @test
     */
    public function test_iframes_and_styles_are_removed(): void
    {
        $payload = '<iframe src="https://evil.example"></iframe>'
            .'<style>body{display:none}</style>'
            .'<div style="position:fixed;inset:0">Tutup</div>'
            .'<p style="color:red">Teks</p>';

        $stored = (string) $this->sanitizer->clean($payload);

        $this->assertStringNotContainsString('<iframe', $stored);
        $this->assertStringNotContainsString('<style', $stored);
        $this->assertStringNotContainsString('position:fixed', $stored);
        $this->assertStringNotContainsString('inset:0', $stored);

        // The text survives; only the attribute is stripped. A sanitiser that
        // discards the content is not a feature.
        $this->assertStringContainsString('Teks', $stored);
    }

    /**
     * @test
     */
    public function test_formatting_an_editor_needs_is_kept(): void
    {
        $payload = '<h2>Judul</h2><p><strong>Tebal</strong> dan <em>miring</em>.</p>'
            .'<ul><li>Satu</li></ul><a href="https://sekolah.sch.id">Tautan</a>';

        $stored = (string) $this->sanitizer->clean($payload);

        $this->assertStringContainsString('<h2>', $stored);
        $this->assertStringContainsString('<strong>', $stored);
        $this->assertStringContainsString('<em>', $stored);
        $this->assertStringContainsString('<li>', $stored);
        $this->assertStringContainsString('href=', $stored);
    }

    /**
     * @test
     */
    public function test_a_new_window_link_gets_noopener(): void
    {
        // target="_blank" without rel hands the opened page a reference back to
        // this one. That is a real phishing vector, not a theoretical one.
        $stored = (string) $this->sanitizer->clean(
            '<a href="https://sekolah.sch.id" target="_blank">X</a>'
        );

        $this->assertStringContainsString('target="_blank"', $stored);
        $this->assertStringContainsString('noopener', $stored);
    }

    /**
     * @test
     */
    public function test_an_update_is_also_cleaned(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Awal', 'body' => '<p>Aman.</p>']);

        $this->cms->update($post, $this->editor(), ['body' => '<p>Baru</p><script>alert(2)</script>']);

        $stored = (string) Post::findOrFail($post->id)->body;

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringContainsString('Baru', $stored);
    }

    /**
     * @test
     */
    public function test_a_restore_cannot_reintroduce_markup(): void
    {
        // A restore is a write path too. An old revision, or a payload edited by
        // hand, must not be a way around the sanitiser.
        $post = $this->cms->create($this->editor(), ['title' => 'Awal', 'body' => '<p>Aman.</p>']);

        // create() writes no revision — there is no prior state to snapshot on
        // a first save — so a revision exists only after the first update. That
        // is why this test updates before reaching for one.
        $this->cms->update($post, $this->editor(), ['body' => '<p>Direvisi.</p>']);

        $revision = $post->revisions()->latest('id')->firstOrFail();

        $revision->update(['payload' => array_merge($revision->payload, [
            'body' => '<p>Restore.</p><script>alert(3)</script>',
        ])]);

        $restored = $this->cms->restoreRevision($post->refresh(), $revision->fresh(), $this->editor());

        $this->assertStringNotContainsString('<script', (string) $restored->body);
        $this->assertStringContainsString('Restore.', (string) $restored->body);
    }

    /**
     * @test
     */
    public function test_an_excerpt_is_derived_and_never_contains_tags(): void
    {
        $post = $this->cms->create($this->editor(), [
            'title' => 'Berita',
            'body' => '<p>'.str_repeat('Kalimat berita yang panjang. ', 40).'</p>',
        ]);

        $excerpt = (string) $post->fresh()->excerpt;

        $this->assertNotSame('', $excerpt);
        $this->assertStringNotContainsString('<', $excerpt);
        $this->assertLessThanOrEqual(181, mb_strlen($excerpt));
    }

    /**
     * @test
     */
    public function test_script_content_never_becomes_plain_text(): void
    {
        // strip_tags() removes the <script> TAG but keeps what was inside it, so
        // "<script>alert(1)</script>" became the text "alert(1)" — which reached
        // the meta description and every listing card. Nothing executed, but a
        // payload's text was being published.
        $text = $this->sanitizer->toText('<p>Galat.</p><script>alert(1)</script>');

        $this->assertStringContainsString('Galat.', $text);
        $this->assertStringNotContainsString('alert(1)', $text);
        $this->assertStringNotContainsString('script', $text);
    }

    /**
     * @test
     */
    public function test_a_truncated_script_tag_is_also_dropped(): void
    {
        // A half-finished paste is the case a well-formed regex misses.
        $text = $this->sanitizer->toText('<p>Aman.</p><script>alert(1)');

        $this->assertStringContainsString('Aman.', $text);
        $this->assertStringNotContainsString('alert(1)', $text);
    }

    /**
     * @test
     */
    public function test_plain_text_is_left_alone(): void
    {
        // A paragraph with no markup must not come back wrapped in tags.
        $this->assertSame('Teks biasa', $this->sanitizer->clean('Teks biasa'));
    }
}
