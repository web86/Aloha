<?php
/** Read-only badge trace; no metadata writes or provider calls. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { exit(1); }
foreach ([186, 365] as $id) {
    $stored = get_post_meta($id, AlohaWPBridge\RentalListingBadge::META, true);
    $first = get_post_meta($id, AlohaWPBridge\RentalListingBadge::FIRST, true);
    $time = is_string($first) && $first !== '' ? strtotime($first) : false;
    $effective = AlohaWPBridge\RentalListingBadge::effective($id);
    $dto = AlohaWPBridge\RentalCatalogData::get($id);
    ob_start(); if ($dto) { aloha_rental_related_card($dto); } $html = ob_get_clean();
    $reason = match (true) {
        $effective === 'luxe' => 'active LUXE', $effective === 'new' => 'active seven-day NEW',
        $stored !== 'new' => 'no badge selected', $time === false => 'first publication unconfirmed',
        $time > time() => 'first publication is in the future',
        time() - $time >= 7 * DAY_IN_SECONDS => 'NEW expired under the existing seven-day rule',
        default => 'invalid stored badge',
    };
    WP_CLI::line(wp_json_encode(['id'=>$id,'stored'=>$stored,'firstPublishedAt'=>$first ?: null,
        'ageDays'=>$time === false ? null : round((time()-$time)/DAY_IN_SECONDS,3), 'effective'=>$effective,
        'catalogPublic'=>$dto !== null,'dtoBadge'=>$dto['badge'] ?? null,
        'htmlBadge'=>str_contains($html,'aloha-related-badge'),'reason'=>$reason]));
}
