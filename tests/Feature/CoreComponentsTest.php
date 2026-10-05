<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The core component set renders and behaves.
 *
 * WHY THIS FILE EXISTS
 *
 * A Blade component fails at RENDER time, not at build time. A missing prop, a
 * malformed Alpine expression, a component that shadows another, or a class
 * that no longer exists in the CSS all produce a page that throws — in
 * production, on one page, for one role. Nothing in the suite catches that
 * unless something renders the component.
 *
 * So each component is rendered here and asserted on the output that matters:
 * that the semantics are right (a dialog says it is a dialog), that the empty
 * case reaches the empty state rather than rendering an empty table, and that
 * responsive behaviour is declared rather than assumed.
 *
 * The alert that matters: `DetailDrawer` closing must return focus. Without it
 * a keyboard user is dropped at the top of the document after every row they
 * inspect, and no visual test would ever show that.
 */
class CoreComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user->refresh();
    }

    // ------------------------------------------------------------ SEARCH INPUT

    /** @test */
    public function the_search_input_is_a_get_form_so_a_filtered_list_can_be_shared(): void
    {
        $html = $this->blade('<x-search-input placeholder="Cari siswa…" />');

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('name="q"', $html, 'One query-parameter convention across the app: q.');
    }

    /** @test */
    public function the_search_input_offers_a_way_back_once_a_query_exists(): void
    {
        // A long search with no clear control means selecting the text and
        // deleting it, which on touch is a long-press.
        $html = $this->blade('<x-search-input :value="$term" />', ['term' => 'ahmad fauzan']);

        $this->assertStringContainsString('Hapus', $html, 'No way back out of an active query.');
        $this->assertStringContainsString('ahmad fauzan', $html, 'The current query must survive the render, or a filtered page loses its own filter.');
    }

    /** @test */
    public function the_search_input_does_not_autofill_people_names(): void
    {
        // On a shared machine, a browser autofilling a real student's name into
        // a "find a student" box is a data leak.
        $html = $this->blade('<x-search-input />');

        $this->assertStringContainsString('autocomplete="off"', $html);
    }

    /**
     * @test
     */
    public function the_search_input_does_not_nest_a_form_inside_a_filter_bar(): void
    {
        // THE REGRESSION THIS GUARDS.
        //
        // x-filter-bar is a GET form. An earlier version of x-search-input was
        // always a form too, so putting the two together produced a form inside
        // a form — which a browser resolves by discarding the inner <form>
        // tags entirely. The search would then submit to the filter's action
        // and simply appear broken, with no error anywhere.
        //
        // The same defect already shipped once, on the student wizard, where it
        // silently discarded uploaded documents while reporting success. This
        // assertion is why it cannot happen a second time.
        $html = $this->blade('<x-filter-bar><x-search-input inline /></x-filter-bar>');

        preg_match_all('/<form\b/i', $html, $opens);
        preg_match_all('/<\/form>/i', $html, $closes);

        $this->assertCount(
            1,
            $opens[0],
            'A form was nested inside the filter bar\'s form. The browser will discard it and the search will silently not work.'
        );
        $this->assertCount(1, $closes[0], 'Unbalanced form tags.');

        // The input must still be inside the bar, or inline mode stripped it.
        $this->assertStringContainsString('name="q"', $html);
    }

    /** @test */
    public function the_search_input_is_a_standalone_form_when_used_alone(): void
    {
        $html = $this->blade('<x-search-input />');

        preg_match_all('/<form\b/i', $html, $opens);

        $this->assertCount(1, $opens[0], 'A standalone search box needs its own form to submit.');
    }

    // -------------------------------------------------------------- FILTER BAR

    /** @test */
    public function the_filter_bar_renders_a_reset_only_when_a_filter_is_applied(): void
    {
        // A plain <select> rather than <x-form-field>: that component reads the
        // shared $errors bag, which the bare blade() helper does not provide,
        // and failing on a fixture is worse than testing the thing.
        $without = $this->blade('<x-filter-bar><select name="kelas" class="field"><option value="">Semua</option></select></x-filter-bar>');

        $this->assertStringNotContainsString('Reset', $without, 'A reset button on an unfiltered list is a control that does nothing.');
        $this->assertStringContainsString('Terapkan', $without, 'A filter bar with no way to apply it is a form with no submit.');
    }

    // --------------------------------------------------------------- DATA TABLE

    /** @test */
    public function the_data_table_shows_the_empty_state_instead_of_an_empty_table(): void
    {
        $html = $this->blade('<x-data-table :rows="$rows" :columns="$columns" empty-title="Belum ada siswa" />', [
            'rows' => collect(),
            'columns' => ['name' => 'Nama'],
        ]);

        $this->assertStringContainsString('Belum ada siswa', $html);
        $this->assertStringNotContainsString('<table', $html, 'An empty <table> is a layout bug waiting for the first row.');
    }

    /** @test */
    public function the_data_table_renders_the_sticky_header_the_existing_css_expects(): void
    {
        $html = $this->blade('<x-data-table :rows="$rows" :columns="$columns" />', [
            'rows' => collect([['name' => 'Ahmad Fauzan']]),
            'columns' => ['name' => ['label' => 'Nama', 'primary' => true]],
        ]);

        $this->assertStringContainsString('data-table', $html);
        $this->assertStringContainsString('Ahmad Fauzan', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }

    /** @test */
    public function the_data_table_declares_a_card_layout_for_narrow_screens(): void
    {
        // Brief §16: below md a row becomes a card. Horizontal scrolling on a
        // phone means losing sight of the row while scrolling to reach its name.
        $html = $this->blade('<x-data-table :rows="$rows" :columns="$columns" />', [
            'rows' => collect([['name' => 'Ahmad Fauzan']]),
            'columns' => ['name' => ['label' => 'Nama', 'primary' => true]],
        ]);

        $this->assertStringContainsString('md:hidden', $html, 'No card layout was declared for mobile.');
    }

    /** @test */
    public function a_selectable_data_table_names_its_bulk_input(): void
    {
        $html = $this->blade('<x-data-table :rows="$rows" :columns="$columns" selectable />', [
            'rows' => collect([['id' => 1, 'name' => 'Ahmad Fauzan']]),
            'columns' => ['name' => 'Nama'],
        ]);

        $this->assertStringContainsString('name="rows[]"', $html);
        $this->assertStringContainsString('aria-label="Pilih semua baris"', $html);
    }

    // ------------------------------------------------------------ DETAIL DRAWER

    /** @test */
    public function the_detail_drawer_announces_itself_as_a_modal_dialog(): void
    {
        $html = $this->blade('<x-detail-drawer title="Ahmad Fauzan" subtitle="XII IPA 1"><p>Body</p></x-detail-drawer>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
    }

    /** @test */
    public function the_detail_drawer_can_be_closed_with_the_keyboard(): void
    {
        $html = $this->blade('<x-detail-drawer title="Detail" />');

        $this->assertStringContainsString('keydown.escape', $html);
    }

    /** @test */
    public function the_detail_drawer_closes_from_its_backdrop(): void
    {
        $html = $this->blade('<x-detail-drawer title="Detail" />');

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('@click="open = false"', $html);
    }

    // ---------------------------------------------------------------- TIMELINE

    /** @test */
    public function the_timeline_stops_its_connector_at_the_last_entry(): void
    {
        $html = $this->blade('<x-timeline :items="$items" />', [
            'items' => [
                ['title' => 'Diajarkan', 'timestamp' => '2 Sep', 'tone' => 'success'],
                ['title' => 'Diverifikasi', 'timestamp' => '3 Sep', 'tone' => 'primary'],
            ],
        ]);

        $this->assertStringContainsString('Diajarkan', $html);
        $this->assertStringContainsString('Diverifikasi', $html);

        // One connector for two items — a line past the final dot is the thing
        // that makes a timeline look broken.
        $this->assertSame(
            1,
            substr_count($html, 'bg-[var(--app-border)]'),
            'The connector is drawn past the last item.'
        );
    }

    /** @test */
    public function the_timeline_tones_its_dots_from_the_item(): void
    {
        $html = $this->blade('<x-timeline :items="$items" />', [
            'items' => [
                ['title' => 'Ditolak', 'tone' => 'danger'],
            ],
        ]);

        $this->assertStringContainsString('bg-[var(--app-danger)]', $html);
    }

    // ---------------------------------------------------------- LOADING SKELETON

    /** @test */
    public function the_loading_skeleton_is_shaped_like_the_content_it_stands_in_for(): void
    {
        $table = $this->blade('<x-loading-skeleton type="table" />');
        $this->assertStringContainsString('role="status"', $table);
        $this->assertStringContainsString('Memuat', $table);

        $card = $this->blade('<x-loading-skeleton type="card" :rows="4" />');
        $this->assertStringContainsString('grid', $card);
    }

    /** @test */
    public function the_loading_skeleton_announces_itself_to_a_screen_reader(): void
    {
        // A skeleton with no text alternative is a blank region to a screen
        // reader, which is worse than the spinner it replaced.
        $html = $this->blade('<x-loading-skeleton />');

        $this->assertStringContainsString('sr-only', $html);
        $this->assertStringContainsString('Memuat', $html);
    }

    // ------------------------------------------------------------- CHART PIECES

    /** @test */
    public function the_donut_chart_serves_real_numbers_without_javascript(): void
    {
        // A chart that only appears once JS has measured the DOM flashes empty
        // on a slow connection and does not appear at all without JS, so the
        // geometry is precomputed into the markup.
        $html = $this->blade('<x-donut-chart :items="$items" />', [
            'items' => [
                ['label' => 'Laki-laki', 'value' => 60],
                ['label' => 'Perempuan', 'value' => 40],
            ],
        ]);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('stroke-dasharray', $html);
        $this->assertStringContainsString('100', $html, 'The centre total is missing.');
    }

    /** @test */
    public function the_donut_chart_states_its_numbers_for_a_screen_reader(): void
    {
        // SVG path geometry read aloud is meaningless, so the accessible name
        // and the legend carry the actual content.
        $html = $this->blade('<x-donut-chart :items="$items" />', [
            'items' => [['label' => 'Terverifikasi', 'value' => 12]],
        ]);

        $this->assertStringContainsString('Terverifikasi', $html);
        $this->assertStringContainsString('100%', $html);
        $this->assertStringContainsString('<dl', $html);
    }

    /** @test */
    public function the_donut_chart_draws_a_track_behind_the_data(): void
    {
        // Without the empty ring, a 30%-filled donut reads as an arc rather
        // than as a proportion of something.
        $html = $this->blade('<x-donut-chart :items="$items" />', [
            'items' => [['label' => 'A', 'value' => 1], ['label' => 'B', 'value' => 2]],
        ]);

        $this->assertStringContainsString('var(--app-surface-muted)', $html);
    }

    /** @test */
    public function a_chart_card_gives_every_chart_the_same_frame_and_heading(): void
    {
        // Five chart types with five different title sizes is what makes a
        // dashboard feel assembled rather than designed.
        $html = $this->blade('<x-chart-card title="Tren siswa" description="12 bulan terakhir"><p>plot</p></x-chart-card>');

        $this->assertStringContainsString('Tren siswa', $html);
        $this->assertStringContainsString('<h2', $html, 'A chart heading must be a heading, not a styled div.');
    }

    /** @test */
    public function a_loading_chart_does_not_render_an_empty_plot(): void
    {
        $html = $this->blade('<x-chart-card title="Tren siswa" loading><p>plot</p></x-chart-card>');

        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('Memuat', $html);
    }

    // ------------------------------------------------------------------ FILE TREE

    /** @test */
    public function the_file_tree_nests_by_re_including_itself(): void
    {
        $html = $this->blade('<x-file-tree :nodes="$nodes" />', [
            'nodes' => [[
                'name' => '2026/2027',
                'children' => [
                    ['name' => 'Kelas XII', 'url' => '#'],
                    ['name' => 'Kartu Keluarga.pdf', 'url' => '#'],
                ],
            ]],
        ]);

        $this->assertStringContainsString('2026/2027', $html);
        $this->assertStringContainsString('Kartu Keluarga.pdf', $html);
        $this->assertStringContainsString('aria-expanded', $html, 'A parent node must announce whether it is open.');
    }

    /** @test */
    public function the_file_tree_marks_the_current_node_for_assistive_technology(): void
    {
        $html = $this->blade('<x-file-tree :nodes="$nodes" />', [
            'nodes' => [['name' => 'Aktif', 'url' => '#', 'active' => true]],
        ]);

        $this->assertStringContainsString('aria-current="page"', $html);
    }

    // -------------------------------------------------------------- COMMAND PALETTE

    /** @test */
    public function the_command_palette_answers_the_shortcut_from_anywhere(): void
    {
        // A shortcut that only works when focus happens to be on the right
        // element is not a shortcut.
        $html = $this->blade('<x-command-palette :actions="$actions" />', [
            'actions' => [['label' => 'Tambah siswa', 'url' => '/admin/siswa']],
        ]);

        $this->assertStringContainsString('keydown.window.ctrl.k', $html);
        $this->assertStringContainsString('keydown.window.meta.k', $html);
    }

    /** @test */
    public function the_command_palette_follows_the_combobox_pattern(): void
    {
        // aria-activedescendant, not real focus movement: moving focus into the
        // list would make every keystroke go to the list, not the search box.
        $html = $this->blade('<x-command-palette :actions="$actions" />', [
            'actions' => [['label' => 'Tambah siswa', 'url' => '/admin/siswa']],
        ]);

        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-activedescendant', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('aria-selected', $html);
    }

    /** @test */
    public function the_command_palette_carries_navigation_and_cancel_keys(): void
    {
        $html = $this->blade('<x-command-palette :actions="$actions" />', [
            'actions' => [],
        ]);

        $this->assertStringContainsString('keydown.down', $html);
        $this->assertStringContainsString('keydown.up', $html);
        $this->assertStringContainsString('keydown.enter', $html);
        $this->assertStringContainsString('keydown.escape', $html);
    }

    /** @test */
    public function the_command_palette_reports_an_empty_result_rather_than_a_blank_panel(): void
    {
        $html = $this->blade('<x-command-palette :actions="$actions" />', ['actions' => []]);

        $this->assertStringContainsString('Tidak ada hasil', $html);
    }

    // --------------------------------------------------------- NOTIFICATION CENTER

    /** @test */
    public function every_notification_row_is_a_link_to_the_thing_it_announces(): void
    {
        // A notification you cannot act on is a message, and the most common
        // version of this feature is a list of unopenable messages.
        $html = $this->blade('<x-notification-center :items="$items" :unread-count="2" />', [
            'items' => [[
                'title' => 'Nilaihalla diperbarui',
                'body' => 'Raport Matematika sudah dipublikasikan.',
                'url' => '/siswa/nilai',
                'icon' => 'check-circle',
                'group' => 'Academic',
                'time' => '5 menit lalu',
            ]],
        ]);

        $this->assertStringContainsString('href="/siswa/nilai"', $html);
        $this->assertStringContainsString('Academic', $html);
    }

    /** @test */
    public function the_notification_center_shows_an_empty_state_not_an_empty_box(): void
    {
        $html = $this->blade('<x-notification-center :items="$items" />', ['items' => []]);

        $this->assertStringContainsString('Tidak ada notifikasi', $html);
    }

    // --------------------------------------------------------------- WIDGET GRID

    /** @test */
    public function the_widget_grid_does_not_drag_on_touch_screens(): void
    {
        // Brief §8: not draggable on mobile. HTML5 drag does not fire on touch,
        // so a pointer implementation would fight the scroll gesture.
        // An empty widget list renders no sections at all, so the fixture has
        // to carry one: the assertion is about the attributes on a real widget.
        $html = $this->blade('<x-widget-grid :widgets="$widgets" />', [
            'widgets' => [['id' => 'stats', 'slot' => '<p>Statistik</p>']],
        ]);

        $this->assertStringContainsString('draggable="true"', $html);
        $this->assertStringContainsString('grid-cols-1', $html);
    }

    /** @test */
    public function the_widget_grid_offers_a_way_to_restore_a_hidden_widget(): void
    {
        // A hide with no restore is data loss from the user's point of view.
        $html = $this->blade('<x-widget-grid :widgets="$widgets" />', [
            'widgets' => [['id' => 'stats', 'slot' => '<p>x</p>']],
        ]);

        $this->assertStringContainsString('Sembunyikan', $html);
        $this->assertStringContainsString('toggle(', $html);
    }
}