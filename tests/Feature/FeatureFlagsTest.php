<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\AvailablePageResolver;
use App\Support\FeatureRegistry;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            ['dashboard', 'dashboard', 'Dashboard'],
            ['plan', 'trips.create', 'trips/Editor'],
            ['notes', 'notes.index', 'notes/Note'],
            ['recommendations', 'recommendations.index', 'recommendations/Recommendation'],
            ['export', 'dashboard.export', 'exports/Export'],
            ['publish', 'dashboard.publish', 'publishing/Publish'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_states_and_shared_props(string $key, string $route, string $component): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component($component)->where("features.{$key}", 'enabled'));
        config(["features.{$key}.state" => 'maintenance']);
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('FeatureMaintenance')->where("features.{$key}", 'maintenance')
            ->missing('trip')->missing('trips')->missing('waypointTagPresets'));
        $this->get(route($route), ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        ])->assertOk()->assertJsonPath('component', 'FeatureMaintenance')
            ->assertJsonMissingPath('props.trip')->assertJsonMissingPath('props.trips');
        config(["features.{$key}.state" => 'hidden']);
        $this->get(route($route))->assertNotFound();
    }

    public function test_unavailable_writes_preserve_all_saved_data(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'Original', 'notes' => 'Original notes']);
        $point = $trip->waypoints()->create(['point_lat' => 50, 'point_lng' => 14, 'order' => 0, 'name' => 'Original stop']);
        $point->tags()->create(['name' => 'Original tag']);
        $trip->tags()->create(['name' => 'Original trip tag']);
        $this->actingAs($user);
        $metadata = ['name' => 'Changed', 'notes' => 'Changed', 'tags' => ['Changed']];
        $notes = ['notes' => 'Changed', 'tags' => ['Changed'], 'waypoints' => [
            ['id' => $point->id, 'name' => 'Changed', 'description' => 'Changed', 'tags' => ['Changed']],
        ]];
        foreach (['hidden' => 404, 'maintenance' => 503] as $state => $status) {
            config(['features.plan.state' => $state, 'features.notes.state' => $state]);
            $this->post(route('trips.store'), ['name' => 'New', 'waypoints' => [
                ['point_lat' => 50, 'point_lng' => 14], ['point_lat' => 51, 'point_lng' => 15],
            ]])->assertStatus($status);
            $this->patch(route('trips.update', $trip), $metadata)->assertStatus($status);
            $this->delete(route('trips.destroy', $trip))->assertStatus($status);
            $this->patch(route('notes.update', $trip), $notes)->assertStatus($status);
            $response = $this->get(route('trips.edit', $trip));
            $response->assertStatus($state === 'hidden' ? 404 : 200);
            $this->assertDatabaseCount('trips', 1);
            $this->assertSame('Original notes', $trip->fresh()->notes);
            $this->assertSame('Original', $trip->fresh()->name);
            $this->assertSame('Original stop', $point->fresh()->name);
            $this->assertSame(['Original tag'], $point->tags()->pluck('name')->all());
            $this->assertSame(['Original trip tag'], $trip->tags()->pluck('name')->all());
        }
        config(['features.plan.state' => 'enabled']);
        $this->patch(route('trips.update', $trip), $metadata)->assertRedirect(route('trips.edit', $trip));
        $this->assertSame('Changed', $trip->fresh()->notes);
    }

    public function test_independence_and_delete_redirect(): void
    {
        $user = User::factory()->create();
        $trip = $user->trips()->create(['name' => 'Walk']);
        $this->actingAs($user);
        config(['features.dashboard.state' => 'hidden', 'features.notes.state' => 'maintenance']);
        $this->get(route('trips.create'))->assertOk();
        $this->get(route('dashboard.export'))->assertOk();
        $this->get(route('dashboard.publish'))->assertOk();
        $this->delete(route('trips.destroy', $trip))->assertRedirect(route('trips.create'));
        config(['features.plan.state' => 'hidden', 'features.notes.state' => 'enabled']);
        $this->get(route('notes.index'))->assertOk();
    }

    public function test_login_rejects_unavailable_and_unsafe_intended_urls(): void
    {
        $user = User::factory()->create();
        config(['features.dashboard.state' => 'hidden']);
        foreach ([route('dashboard'), 'https://example.org/notes', '//example.org', '/\\example.org', '/login', '/missing', '/notes?trip[]=1', '/notes?trip=99999'] as $url) {
            $this->withSession(['url.intended' => $url])->post(route('login'), [
                'email' => $user->email, 'password' => 'password',
            ])->assertRedirect(route('trips.create'));
            $this->post(route('logout'));
        }
        $this->withSession(['url.intended' => route('notes.index')])->post(route('login'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('notes.index'));
    }

    public function test_all_unavailable_states_fall_back_to_home_without_loop(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['hidden', 'maintenance'] as $state) {
            foreach (array_keys(config('features')) as $key) {
                config(["features.{$key}.state" => $state]);
            }
            $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')->where('appEntryUrl', route('home')));
            $this->get(route('login'))->assertRedirect(route('home'));
            $this->post(route('password.confirm'), ['password' => 'password'])->assertRedirect(route('home'));
            $this->get(route('verification.notice'))->assertRedirect(route('home'));
            $this->post(route('verification.send'))->assertRedirect(route('home'));
        }
    }

    public function test_registration_and_verification_use_available_page(): void
    {
        config(['features.dashboard.state' => 'maintenance']);
        $this->post(route('register'), ['name' => 'Example', 'email' => 'example@example.test',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect(route('trips.create'));
        $user = User::where('email', 'example@example.test')->firstOrFail();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]))->assertRedirect(route('trips.create').'?verified=1');
    }

    public function test_resolver_respects_verification_and_unknown_access_rules(): void
    {
        $user = new class extends User implements MustVerifyEmail {};
        $user->email_verified_at = null;
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $this->assertSame(route('trips.create'), app(AvailablePageResolver::class)->entryUrl($request));
        Route::get('/restricted-test', fn () => 'restricted')->middleware(['auth', 'custom-access'])->name('restricted-test');
        config(['features' => ['restricted' => ['state' => 'enabled', 'entry_route' => 'restricted-test']]]);
        $this->assertSame(route('home'), app(AvailablePageResolver::class)->entryUrl($request));
    }

    public function test_registry_extends_without_new_middleware_and_fails_closed(): void
    {
        Route::get('/calendar-test', fn () => Inertia::render('Welcome', ['secret' => 'original']))
            ->middleware(['web', 'auth', 'feature:calendar,page'])->name('calendar-test');
        Route::get('/calendar-download', fn () => 'file')->middleware(['web', 'auth', 'feature:calendar']);
        $this->actingAs(User::factory()->create());
        $this->get('/calendar-test')->assertNotFound();
        foreach ([true, null, 'invalid'] as $invalid) {
            config(['features.calendar.state' => $invalid]);
            $this->get('/calendar-test')->assertNotFound();
        }
        config(['features.calendar' => ['state' => 'maintenance', 'label' => 'Calendar']]);
        $this->get('/calendar-test')->assertInertia(fn (Assert $page) => $page->component('FeatureMaintenance')->missing('secret'));
        $this->get('/calendar-download')->assertStatus(503);
        config(['features.calendar.state' => 'enabled']);
        $this->get('/calendar-test')->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('secret', 'original'));
        $this->assertSame('hidden', app(FeatureRegistry::class)->state('unknown'));
    }

    public function test_multiple_feature_requirements_do_not_expose_a_hidden_page(): void
    {
        Route::get('/combined-features', fn () => Inertia::render('Welcome', ['secret' => 'original']))
            ->middleware(['web', 'auth', 'feature:plan,page', 'feature:notes,page']);
        $this->actingAs(User::factory()->create());
        config(['features.plan.state' => 'maintenance', 'features.notes.state' => 'hidden']);
        $this->get('/combined-features')->assertNotFound();
        config(['features.notes.state' => 'enabled']);
        $this->get('/combined-features')->assertInertia(fn (Assert $page) => $page
            ->component('FeatureMaintenance')->missing('secret'));
        $this->head('/combined-features')->assertOk();
    }

    public function test_registered_routes_have_protection(): void
    {
        $expected = ['dashboard' => 'dashboard', 'dashboard.export' => 'export', 'dashboard.publish' => 'publish',
            'trips.create' => 'plan', 'trips.store' => 'plan', 'trips.edit' => 'plan', 'trips.update' => 'plan',
            'trips.destroy' => 'plan', 'notes.index' => 'notes', 'notes.update' => 'notes', 'recommendations.index' => 'recommendations'];
        foreach ($expected as $name => $feature) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $kind = in_array('GET', $route->methods(), true) ? ',page' : '';
            $this->assertContains("feature:{$feature}{$kind}", $route->gatherMiddleware());
        }
        $this->assertNull(Route::getRoutes()->getByName('recommendations.store'));
    }
}
