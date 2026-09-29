<?php
/**
 * Summarises sandbox orders for tests/sandbox/sandbox.sh inspect.
 *
 * Run with wp eval-file, which evals the file, so no strict_types declaration.
 * ORDER_IDS is a comma-separated list.
 */

foreach (array_filter(explode(',', (string) getenv('ORDER_IDS'))) as $order_id) {
    $order = wc_get_order((int) $order_id);
    if (!$order) {
        printf("#%d not found\n", $order_id);
        continue;
    }

    $keys = [];
    foreach ($order->get_meta_data() as $meta) {
        $key = $meta->get_data()['key'];
        if (strpos($key, '_bci_woo_') === 0) {
            $keys[] = $key;
        }
    }
    $duplicated = array_keys(array_filter(array_count_values($keys), static fn ($count) => $count > 1));

    printf(
        "#%d %-10s paid=%s duplicated_meta=%s\n",
        $order->get_id(),
        $order->get_status(),
        $order->get_date_paid() ? 'yes' : 'no',
        $duplicated ? implode(',', $duplicated) : 'none'
    );

    foreach (array_reverse(wc_get_order_notes(['order_id' => $order->get_id()])) as $note) {
        printf("    %s %s\n", $note->date_created->date('H:i:s'), wp_strip_all_tags($note->content));
    }
}
