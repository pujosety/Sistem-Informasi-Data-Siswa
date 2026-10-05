<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every public page renders, including its footer.
 *
 * THE BUG THIS PINS
 *
 * `public/layout.blade.php` had a footer navigation whose active-link logic read
 * a variable called `$current`. No controller has ever passed it — it was left
 * over from an earlier layout. Because every use sat inside an `@class()`
 * condition and the footer is below the fold, it read as a styling detail and
 * survived review.
 *
 * It is a 500 on every public page, which is the whole public-facing school
 * website. The reason it was not caught: no test rendered a public page with a
 * request that reached the footer, and `PublicHomePageTest` passed because the
 * homepage's own content rendered before the failure.
 *
 * So this asserts the STATUS on every public route, not the presence of a
 * string. A page that 500s cannot satisfy any content assertion.
 */
class PublicPageRendersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seven explicit assertions rather than a data provider.
     *
     * A provider this runner does not resolve yields a test that silently does
     * not run — and "no failure" is exactly what a test that never executed
     * also looks like. The status code is the assertion, so each route has to
     * reach it on its own.
     *
     * @test
     */
    public function the_home_page_renders(): void
    {
        $this->get(route('home'))->assertOk();
    }

    /** @test */
    public function the_home_page_degrades_when_landing_schema_is_not_migrated(): void
    {
        Schema::dropIfExists('landing_sections');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('LYFLA');
    }

    /** @test */
    public function the_about_page_renders(): void
    {
        $this->get(route('public.about'))->assertOk();
    }

    /** @test */
    public function the_programs_page_renders(): void
    {
        $this->get(route('public.programs'))->assertOk();
    }

    /** @test */
    public function the_admission_page_renders(): void
    {
        $this->get(route('public.admission'))->assertOk();
    }

    /** @test */
    public function the_contact_page_renders(): void
    {
        $this->get(route('public.contact'))->assertOk();
    }

    /** @test */
    public function the_news_index_renders(): void
    {
        $this->get(route('public.news'))->assertOk();
    }

    /** @test */
    public function the_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk();
    }

    /** @test */
    public function the_public_footer_is_present_on_every_public_page(): void
    {
        // `login` is excluded on purpose: a sign-in screen is deliberately
        // chrome-free, and asserting a site footer there would be asserting the
        // wrong design. The variable bug lived in the public layout, which the
        // login page does not use.
        foreach (['home', 'public.about', 'public.programs', 'public.admission', 'public.contact', 'public.news'] as $routeName) {
            $html = $this->get(route($routeName))->assertOk()->getContent();

            $this->assertStringContainsString(
                '<footer',
                $html,
                "The footer is missing on {$routeName}. Its navigation is where the undefined-variable bug lived."
            );
        }
    }

    /** @test */
    public function the_public_header_offers_both_doors_and_both_navigations(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Desktop nav and the mobile drawer are separate elements, and the
        // brief is explicit that a visitor on a phone must reach every page.
        $this->assertStringContainsString('aria-label="Navigasi utama"', $html);
        $this->assertStringContainsString('aria-label="Menu navigasi"', $html);

        // Registration is the primary CTA; the portal is present but secondary.
        $this->assertStringContainsString(route('public.admission'), $html);
        $this->assertStringContainsString(route('login'), $html);
    }

    /** @test */
    public function the_public_header_needs_no_session(): void
    {
        // A school website that starts a session to render its header cannot be
        // cached, and cache is what makes a public site fast.
        $this->get(route('home'))->assertOk()->assertSessionMissing('flash_notification');
    }
}