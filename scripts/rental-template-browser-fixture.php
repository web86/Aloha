<?php
/** Synthetic data for the local Rental template browser check. */

use AlohaWPBridge\RentalEditor;
use AlohaWPBridge\RentalPublicData;
use AlohaWPBridge\Rentals;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || home_url() !== 'http://127.0.0.1:8088') {
    exit(1);
}
[$operation, $key] = $args + ['', ''];
if (!in_array($operation, ['create', 'remove'], true) || !preg_match('/\Am24-fixture-[a-f0-9]{16}\z/', $key)) {
    exit(1);
}
global $wpdb;
$listingTable = $wpdb->prefix . 'awb_listing_map';
$mediaTable = $wpdb->prefix . 'awb_media_map';
$assert = static function (bool $ok, string $failure): void {
    if (!$ok) {
        WP_CLI::error($failure);
    }
};

if ($operation === 'remove') {
    $rentalSlugs = array_map(static fn ($mode) => $key . '-' . $mode, ['public', 'draft', 'empty']);
    $rentals = get_posts(['post_type' => 'rental', 'post_status' => 'any', 'post_name__in' => $rentalSlugs, 'posts_per_page' => -1]);
    $areas = get_posts(['post_type' => 'area', 'post_status' => 'publish', 'post_name__in' => [$key . '-area'], 'posts_per_page' => -1]);
    $places = get_posts(['post_type' => 'nearby_place', 'post_status' => 'any',
        's' => $key, 'search_columns' => ['post_title'], 'posts_per_page' => -1]);
    foreach ([...$rentals, ...$places, ...$areas] as $post) {
        if (in_array($post->ID, [186, 365], true)) {
            continue;
        }
        if ($post->post_type === 'nearby_place' && !str_starts_with($post->post_title, $key . ' ')) {
            continue;
        }
        if ($post->post_type === 'rental') {
            $map = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE post_id = %d AND guesty_listing_id = %s',
                $listingTable, $post->ID, 'private-source-' . $post->post_name));
            if (!$map && !str_starts_with($post->post_name, $key . '-')) {
                continue;
            }
            if ($map) {
                $wpdb->delete($mediaTable, ['listing_map_id' => $map]);
                $wpdb->delete($listingTable, ['id' => $map, 'post_id' => $post->ID]);
            }
            foreach (get_children(['post_parent' => $post->ID, 'post_type' => 'attachment']) as $item) {
                if (str_starts_with($item->post_title, $key . ' ')) {
                    wp_delete_attachment($item->ID, true);
                }
            }
        }
        wp_delete_post($post->ID, true);
    }
    return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
$admin = get_user_by('login', getenv('WP_ADMIN_USER'));
$assert($admin && user_can($admin, 'manage_options'), 'Local fixture administrator required.');
wp_set_current_user($admin->ID);

$area = wp_insert_post(['post_type' => 'area', 'post_title' => 'Keauhou', 'post_name' => $key . '-area', 'post_status' => 'publish'], true);
$assert(is_int($area) && $area > 0, 'Could not create synthetic Area.');
$nearby = new WP_REST_Request('POST', '/aloha-content/v1/nearby-places');
$nearby->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$nearby->set_header('Content-Type', 'application/json');
$nearby->set_body(wp_json_encode(['name' => $key . ' Park', 'latitude' => 19.123456, 'longitude' => -155.543217, 'areaId' => $area]));
$place = rest_do_request($nearby);
$assert($place->get_status() === 201, 'Could not create synthetic Nearby Place.');
$placeId = $place->get_data()['id'];

$rentals = [];
foreach (['public', 'draft', 'empty'] as $mode) {
    $guard = [Rentals::class, 'blockNativeWrite'];
    remove_filter('wp_insert_post_empty_content', $guard);
    $title = $mode === 'public' ? 'Lanai at Sunset' : $key . ' ' . ucfirst($mode);
    try {
        $id = wp_insert_post(['post_type' => 'rental', 'post_author' => $admin->ID, 'post_name' => $key . '-' . $mode,
            'post_title' => $title, 'post_status' => 'draft'], true);
    } finally {
        add_filter('wp_insert_post_empty_content', $guard, 10, 2);
    }
    $assert(is_int($id) && $id > 0 && $id !== 186, 'Could not create synthetic Rental.');
    // Drafts otherwise receive their slug on publication.
    $post = get_post($id);
    if (!$post->post_name) {
        wp_update_post(['ID' => $id, 'post_name' => $key . '-' . $mode]);
        $post = get_post($id);
    }
    $guestyId = 'private-source-' . $post->post_name;
    $assert(strlen($guestyId) <= 64 && str_starts_with($post->post_name, $key), 'Fixture identity mismatch.');
    $now = gmdate('Y-m-d H:i:s');
    $assert($wpdb->insert($listingTable, ['connection_key' => 'booking_engine', 'provider_environment' => 'sandbox',
        'guesty_listing_id' => $guestyId, 'post_id' => $id, 'created_at_utc' => $now, 'updated_at_utc' => $now]) === 1, 'Map insert failed.');
    $map = $wpdb->insert_id;
    $snapshot = ['version' => 1, 'id' => $guestyId, 'title' => 'Private source title', 'beVisible' => true,
        'pictures' => [], 'cover' => ['pictureId' => null, 'fallbackKey' => null, 'url' => null], 'address' => 'PRIVATE_FIXTURE_ADDRESS'];

    if ($mode === 'public') {
        $pictures = [];
        $suggestions = [];
        $images = [];
        for ($n = 1; $n <= 14; ++$n) {
            $photoId = 'private-photo-' . $n;
            $pictures[] = ['id' => $photoId, 'caption' => 'Private source caption', 'large' => null, 'regular' => null,
                'original' => 'https://assets.guesty.com/' . $key . '/private-original-' . $n . '.png', 'thumbnail' => null];
            $room = match (true) {
                $n === 5 => 'Patio',
                $n >= 10 => 'Pool',
                $n >= 7 => 'Dining area',
                default => 'Living room',
            };
            $suggestions[$photoId] = ['spaceIds' => ['private-space-' . sanitize_title($room)], 'reason' => 'verified_space', 'suggestedLabel' => $room];
            if ($n === 14) {
                continue;
            }
            [$width, $height] = $n === 5 ? [1400, 2200] : [2200, 1466];
            $image = imagecreatetruecolor($width, $height);
            $sky = imagecolorallocate($image, 143 + $n * 6, 218 - $n * 4, 206 - $n * 5);
            $sun = imagecolorallocate($image, 248, 213, 145);
            $hill = imagecolorallocate($image, 66 + $n * 2, 112, 111);
            imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $sky);
            imagefilledellipse($image, (int) ($width * .78), (int) ($height * .24), 280, 280, $sun);
            imagefilledpolygon($image, [0, $height, (int) ($width * .29), (int) ($height * .38),
                (int) ($width * .52), (int) ($height * .82), (int) ($width * .75), (int) ($height * .53), $width, $height], $hill);
            ob_start();
            imagejpeg($image, null, 88);
            $bytes = ob_get_clean();
            imagedestroy($image);
            $file = wp_upload_bits($key . '-' . $n . '.jpg', null, $bytes);
            $assert(empty($file['error']), 'Fixture media upload failed.');
            $attach = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => $key . ' Image ' . $n,
                'post_excerpt' => 'Local caption ' . $n, 'post_status' => 'inherit', 'guid' => $file['url']], $file['file'], $id, true);
            if (is_wp_error($attach)) {
                wp_delete_file($file['file']);
            }
            $assert(is_int($attach) && $attach > 0, 'Fixture attachment failed.');
            wp_update_attachment_metadata($attach, wp_generate_attachment_metadata($attach, $file['file']));
            update_post_meta($attach, '_wp_attachment_image_alt', 'Local photo ' . $n);
            $assert($wpdb->insert($mediaTable, ['listing_map_id' => $map, 'guesty_picture_id' => $photoId,
                'attachment_id' => $attach, 'created_at_utc' => $now]) === 1, 'Fixture media mapping failed.');
            $images[$n] = $attach;
        }
        $snapshot['pictures'] = $pictures;
        $available = static fn ($value): array => ['status' => 'available', 'value' => $value];
        $source = ['version' => 1, 'source' => [
            'listing' => ['accommodates' => $available(8), 'areaSquareFeet' => $available(2000)],
            'photoLabelSuggestions' => $suggestions,
            'privateLocation' => ['address' => 'PRIVATE_FIXTURE_ADDRESS'],
            'sleepingSpaces' => [['id' => 'private-space-test', 'name' => 'Suite', 'type' => 'BEDROOM',
                'beds' => [['type' => 'KING_BED', 'quantity' => 1]]]],
            'amenities' => ['status' => 'available', 'groups' => [['name' => 'Outdoor', 'amenities' => ['Pool']]], 'unmapped' => []],
            'houseRules' => ['petsAllowed' => $available(false), 'minimumAge' => $available(25),
                'additionalRules' => $available('PRIVATE_FIXTURE_RULES')],
        ]];
        $assert(RentalEditor::writeSourceMeta($id, '_awb_source_data', $source), 'Fixture facts failed.');
        $label = str_repeat('a', 32);
        update_post_meta($id, '_awb_gallery', ['labels' => [['id' => $label, 'name' => 'Ocean view']],
            'photos' => [['photoId' => 'private-photo-2', 'labelId' => $label],
                ['photoId' => 'private-photo-3', 'labelId' => null], ['photoId' => 'private-photo-6', 'visible' => false]],
            'order' => ['private-photo-2', 'private-photo-1', 'private-photo-3']]);
        update_post_meta($id, '_awb_area_id', $area);
        update_post_meta($id, '_awb_usp', 'A quiet place to start and end the day.');
        update_post_meta($id, '_awb_about_summary', 'Synthetic public description.');
        update_post_meta($id, '_awb_highlights', [['attachmentId' => $images[1], 'headline' => 'Ocean air', 'description' => 'Synthetic highlight.', 'order' => 0]]);
        update_post_meta($id, '_awb_nearby_places', [['placeId' => $placeId, 'minutes' => 7, 'mode' => 'min drive', 'order' => 0]]);
        update_post_meta($id, '_awb_featured_reviews', [
            ['sourceId' => str_repeat('a', 64), 'channel' => 'airbnb2', 'value' => 4.5, 'max' => 5,
                'text' => ['body' => 'A fixture Airbnb review.'], 'date' => '2026-10-02', 'order' => 0],
            ['sourceId' => str_repeat('b', 64), 'channel' => 'bookingCom', 'value' => 8.5, 'max' => 10,
                'text' => ['positive' => 'A fixture Booking review.', 'negative' => 'Fixture road noise.'], 'date' => '2026-10-01', 'order' => 1],
        ]);
        $rentals['images'] = $images;
    } elseif ($mode === 'empty') {
        update_post_meta($id, '_awb_area_id', $area);
        set_post_thumbnail($id, $rentals['images'][6]);
    }
    $assert(RentalEditor::writeSourceMeta($id, '_awb_source_snapshot', $snapshot), 'Fixture snapshot failed.');
    if ($mode !== 'draft') {
        $assert(wp_update_post(['ID' => $id, 'post_status' => 'publish'], true) === $id && get_post_status($id) === 'publish', 'Fixture publish failed.');
    }
    $rentals[$mode] = ['id' => $id, 'url' => get_permalink($id), 'title' => $title];
}
$dto = RentalPublicData::get($rentals['public']['id']);
$assert(array_column($dto['gallery']['photos'] ?? [], 'attachmentId') === [
    ...array_map(static fn ($n) => $rentals['images'][$n], [2, 1, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13]),
], 'Published DTO lost mapping, visibility or order.');
$assert(array_column($dto['gallery']['photos'], 'label') === ['Ocean view', 'Living room', null, 'Living room', 'Patio',
    'Dining area', 'Dining area', 'Dining area', 'Pool', 'Pool', 'Pool', 'Pool'],
    'Public DTO lost explicit/default label precedence.');
$assert(RentalPublicData::get($rentals['draft']['id']) === null, 'Draft was eligible for the public DTO.');
WP_CLI::line(wp_json_encode($rentals));
