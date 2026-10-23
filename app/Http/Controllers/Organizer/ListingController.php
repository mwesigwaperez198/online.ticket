<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\TicketType;
use App\Support\Audit;
use App\Support\ImageUpload;
use App\Support\Money;
use App\Support\TicketingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ListingController extends Controller
{
    use OrganizerScope;

    public function index(): View
    {
        return view('organizer.listings.index', [
            'listings' => $this->scopedListings()->with('ticketTypes')->orderByDesc('created_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('organizer.listings.form', [
            'listing' => new Listing(['type' => 'event', 'max_per_order' => 10, 'refund_window_hours' => 72]),
            'ticketTypes' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizer = $this->organizer();

        if ($organizer === null) {
            return back()->with('error', 'Organizer profile missing. Contact support.');
        }

        $validated = $request->validate([
            'type' => ['required', 'in:event,service'],
            'intent' => ['nullable', 'in:draft,submit'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'video_url' => ['nullable', 'string', 'max:500', 'url:https,http'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:20'],
            'refund_window_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'ticket_types' => ['nullable', 'array'],
            'ticket_types.*.name' => ['nullable', 'string', 'max:80'],
            'ticket_types.*.price' => ['nullable', 'numeric', 'min:0'],
            'ticket_types.*.capacity' => ['nullable', 'integer', 'min:1'],
            'cover_image' => ['nullable', ...array_slice(ImageUpload::rules(), 1)],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['nullable', ...array_slice(ImageUpload::rules(), 1)],
        ]);

        foreach ((array) ($validated['ticket_types'] ?? []) as $tier) {
            if (collect($tier)->filter(fn ($value) => filled($value))->isNotEmpty()
                && (! filled($tier['name'] ?? null) || ! filled($tier['price'] ?? null) || ! filled($tier['capacity'] ?? null))) {
                return back()->withErrors(['ticket_types' => 'Complete the name, price and capacity for each ticket tier.'])->withInput();
            }
        }

        $listing = DB::transaction(function () use ($validated, $organizer): Listing {
            $listing = Listing::query()->create([
                'organizer_id' => $organizer->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'venue' => $validated['venue'] ?? null,
                'venue_address' => $validated['venue_address'] ?? null,
                'city' => $validated['city'] ?? null,
                'region' => $validated['region'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'status' => 'draft',
                'max_per_order' => (int) $validated['max_per_order'],
                'refund_window_hours' => (int) $validated['refund_window_hours'],
                'allow_refunds' => true,
            ]);

            foreach ((array) ($validated['ticket_types'] ?? []) as $index => $type) {
                if (blank($type['name'] ?? null)) {
                    continue;
                }

                TicketType::query()->create([
                    'listing_id' => $listing->id,
                    'name' => $type['name'],
                    'description' => $type['description'] ?? null,
                    'price_minor' => Money::toMinor((float) $type['price']),
                    'currency' => Money::currency(),
                    'capacity' => (int) $type['capacity'],
                    'sort_order' => $index,
                    'active' => true,
                ]);
            }

            return $listing;
        });

        if ($request->hasFile('cover_image')) {
            if (! $this->storeCover($listing, $request->file('cover_image'))) {
                return redirect()->route('organizer.listings.edit', $listing)
                    ->with('error', 'The listing was saved, but its banner could not be written to persistent storage. Please retry the upload.');
            }
        }

        if (! $this->storeGalleryImages($listing, (array) $request->file('gallery_images', []))) {
            return redirect()->route('organizer.listings.edit', $listing)
                ->with('error', 'The listing was saved, but one or more gallery photos could not be written to persistent storage. Retry those uploads.');
        }

        $submitted = ($validated['intent'] ?? 'draft') === 'submit'
            && $this->hasSubmissionRequirements($listing);

        if ($submitted) {
            $listing->forceFill(['status' => 'pending'])->save();
            Audit::log('listing.submitted', $listing);
            TicketingNotifier::admin(
                'Event listing awaiting approval',
                $listing->title.' was submitted by '.$organizer->business_name.'.',
                ['label' => 'Review listing', 'url' => route('admin.listings.review', $listing)],
            );
            TicketingNotifier::user(
                $organizer->user,
                'Event submitted for approval',
                'Your listing "'.$listing->title.'" was submitted. We will email you when it is approved or if changes are needed.',
                ['label' => 'View listing', 'url' => route('organizer.listings.edit', $listing)],
            );
        }

        Audit::log('listing.created', $listing, ['type' => $listing->type]);

        return redirect()->route('organizer.listings.edit', $listing)
            ->with($submitted ? 'status' : (($validated['intent'] ?? 'draft') === 'submit' ? 'error' : 'status'), $submitted
                ? 'Submitted for approval.'
                : (($validated['intent'] ?? 'draft') === 'submit'
                    ? 'Your draft was saved, but add at least one ticket tier and a banner image before submitting.'
                    : 'Listing saved as draft. Submit it for approval when ready.'));
    }

    public function edit(Listing $listing): View|RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        return view('organizer.listings.form', [
            'listing' => $listing,
            'ticketTypes' => $listing->ticketTypes()->get(),
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        $wasPublished = $listing->isLive();

        $validated = $request->validate([
            'intent' => ['nullable', 'in:draft,submit'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'video_url' => ['nullable', 'string', 'max:500', 'url:https,http'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:20'],
            'refund_window_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'cover_image' => ['nullable', ...array_slice(ImageUpload::rules(), 1)],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['nullable', ...array_slice(ImageUpload::rules(), 1)],
        ]);

        $galleryFiles = (array) $request->file('gallery_images', []);

        if ($listing->images()->count() + count($galleryFiles) > 12) {
            return back()->withErrors(['gallery_images' => 'An event can have up to 12 gallery photos. Remove an existing photo before adding more.'])->withInput();
        }

        unset($validated['intent'], $validated['cover_image']);
        $listing->fill($validated)->save();

        if ($request->hasFile('cover_image')) {
            if (! $this->storeCover($listing, $request->file('cover_image'))) {
                return back()->withInput()->with('error', 'Your listing details were saved, but the banner could not be written to persistent storage. Please retry the upload.');
            }
        }

        if (! $this->storeGalleryImages($listing, $galleryFiles)) {
            return back()->withInput()->with('error', 'Your listing details were saved, but one or more gallery photos could not be written to persistent storage. Retry those uploads.');
        }

        $wantsSubmission = $request->input('intent', 'draft') === 'submit';
        $submitted = $wantsSubmission && $this->hasSubmissionRequirements($listing);

        if ($wasPublished) {
            $message = 'Listing updated and remains available to buyers.';
        } elseif ($submitted) {
            $listing->forceFill(['status' => 'pending'])->save();
            Audit::log('listing.submitted', $listing);
            TicketingNotifier::admin(
                'Event listing awaiting approval',
                $listing->title.' was submitted for review by '.$listing->organizer->business_name.'.',
                ['label' => 'Review listing', 'url' => route('admin.listings.review', $listing)],
            );
            TicketingNotifier::user(
                $listing->organizer->user,
                'Event submitted for approval',
                'Your listing "'.$listing->title.'" was submitted for review.',
                ['label' => 'View listing', 'url' => route('organizer.listings.edit', $listing)],
            );
            $message = 'Submitted for approval.';
        } elseif ($wantsSubmission) {
            $message = 'Your draft was saved, but add at least one ticket tier and a banner image before submitting.';
        } else {
            $message = 'Listing updated.';
        }

        Audit::log('listing.updated', $listing);

        $flashType = $wasPublished || $submitted || ! $wantsSubmission ? 'status' : 'error';

        return back()->with($flashType, $message);
    }

    public function publish(Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        if (! $this->hasSubmissionRequirements($listing)) {
            return back()->with('error', 'Add at least one ticket tier and a banner image before submitting.');
        }

        $listing->forceFill(['status' => 'pending'])->save();

        Audit::log('listing.submitted', $listing);

        return back()->with('status', 'Submitted for approval.');
    }

    /**
     * Store the event banner image.
     *
     * The upload is validated against a strict MIME allow list and a hard
     * size limit before anything is written to disk, and the stored file name
     * is generated by the platform so the original name can never influence
     * the path on the server.
     */
    public function uploadCover(Request $request, Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        $validated = $request->validate([
            'cover_image' => ImageUpload::rules(),
        ]);

        /** @var UploadedFile $file */
        $file = $validated['cover_image'];
        $extension = ImageUpload::extensionFor($file);

        if ($extension === null) {
            return back()->with('error', 'Only JPEG, PNG or WebP images are accepted.');
        }

        if (! $this->storeCover($listing, $file)) {
            return back()->with('error', 'We could not store that image. Please try again.');
        }

        return back()->with('status', 'Banner image updated.');
    }

    public function deleteImage(Listing $listing, ListingImage $image): RedirectResponse
    {
        if (! $this->owns($listing) || $image->listing_id !== $listing->id) {
            abort(403);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        Audit::log('listing.gallery_image_deleted', $listing, ['image_id' => $image->id]);

        return back()->with('status', 'Gallery photo removed.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        if (! $this->owns($listing)) {
            abort(403);
        }

        if ($listing->orders()->whereIn('status', ['paid', 'pending'])->exists()) {
            return back()->with('error', 'This listing has live orders and cannot be deleted.');
        }

        $listing->delete();

        Audit::log('listing.deleted', $listing);

        return redirect()->route('organizer.listings.index')->with('status', 'Listing deleted.');
    }

    private function owns(Listing $listing): bool
    {
        return $listing->organizer_id === ($this->organizer()?->id ?? 0);
    }

    private function hasSubmissionRequirements(Listing $listing): bool
    {
        return (! $listing->isEvent() || $listing->ticketTypes()->exists())
            && filled($listing->cover_image);
    }

    private function storeCover(Listing $listing, UploadedFile $file): bool
    {
        $extension = ImageUpload::extensionFor($file);

        if ($extension === null) {
            return false;
        }

        $previous = $listing->cover_image;
        $path = $this->storeUploadedImage($file, 'covers/'.$listing->id, $extension);

        if ($path === false) {
            return false;
        }

        $listing->forceFill(['cover_image' => $path])->save();

        if (is_string($previous) && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }

        Audit::log('listing.cover_uploaded', $listing, ['path' => $path]);

        return true;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeGalleryImages(Listing $listing, array $files): bool
    {
        $storedAll = true;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                $storedAll = false;

                continue;
            }

            $extension = ImageUpload::extensionFor($file);

            if ($extension === null) {
                $storedAll = false;

                continue;
            }

            $path = $this->storeUploadedImage($file, 'listing-galleries/'.$listing->id, $extension);

            if ($path === false) {
                $storedAll = false;

                continue;
            }

            $listing->images()->create([
                'path' => $path,
                'sort_order' => $listing->images()->count(),
            ]);
        }

        return $storedAll;
    }

    private function storeUploadedImage(UploadedFile $file, string $directory, string $extension): string|false
    {
        $disk = Storage::disk('public');
        $filename = Str::random(40).'.'.$extension;
        $path = trim($directory, '/').'/'.$filename;

        try {
            if ($disk->putFileAs(trim($directory, '/'), $file, $filename, ['visibility' => 'public'])) {
                return $path;
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return false;
    }
}
