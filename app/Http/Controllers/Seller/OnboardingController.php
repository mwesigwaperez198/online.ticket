<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Support\Audit;
use App\Support\TicketingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The post-signup path that turns a plain buyer into a seller.
 *
 * Novara accounts are not one role for life: anyone signed in can open a
 * seller workspace at any time without re-registering, mirroring the
 * business-name step of registration. The organizer starts as pending, so
 * every listing still passes through admin moderation before it goes live.
 */
final class OnboardingController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->organizer !== null) {
            return redirect()->route('organizer.dashboard');
        }

        return view('seller.onboarding');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->organizer !== null) {
            return redirect()->route('organizer.dashboard');
        }

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
        ]);

        $organizer = Organizer::query()->create([
            'user_id' => $user->id,
            'business_name' => $validated['business_name'],
            'slug' => $this->uniqueSlug($validated['business_name']),
            'status' => 'pending',
        ]);

        $user->forceFill(['role' => 'organizer'])->save();

        Audit::log('seller.onboarded', $organizer, ['business_name' => $organizer->business_name]);

        TicketingNotifier::admin(
            'Organizer application awaiting review',
            $organizer->business_name.' has submitted an organizer application.',
            ['label' => 'Review organizers', 'url' => route('admin.approvals.index')],
        );
        TicketingNotifier::user(
            $user,
            'Organizer application received',
            'Your organizer profile is pending admin approval. We will email you when its status changes.',
            ['label' => 'Organizer dashboard', 'url' => route('organizer.dashboard')],
        );

        return redirect()->route('organizer.dashboard')
            ->with('status', 'Your seller workspace is ready. Create a listing and submit it for moderation.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organizer';
        $slug = $base;
        $i = 2;

        while (Organizer::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
