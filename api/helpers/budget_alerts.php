<?php

declare(strict_types=1);

/**
 * Recalculate the active budget alert for one student/category/month.
 *
 * Rules:
 * - Below 80%: no active unread alert.
 * - 80% to 99.99%: near-limit alert.
 * - Exactly 100%: budget-limit reached alert.
 * - Above 100%: show percentage OVER budget, not total usage.
 * - Only one active unread budget alert per category/month.
 * - Existing unread alerts are updated instead of duplicated.
 */
function refreshBudgetAlert(
    PDO $db,
    int $userId,
    int $categoryId,
    string $transactionDate
): void {
    /*
     * Called from both transaction endpoints (YYYY-MM-DD)
     * and budget upsert (YYYY-MM).
     */
    $dateValue = trim($transactionDate);

    if (
        preg_match(
            '/^\d{4}-\d{2}$/',
            $dateValue
        ) === 1
    ) {
        $dateValue .= '-01';
    }

    $timestamp = strtotime($dateValue);

    if ($timestamp === false) {
        return;
    }

    $monthStart = date(
        'Y-m-01',
        $timestamp
    );

    $monthEnd = date(
        'Y-m-t',
        $timestamp
    );


    /* =====================================================
       CATEGORY
       ===================================================== */

    $categoryQuery = $db->prepare(
        'SELECT name
         FROM categories
         WHERE id = :category_id
         LIMIT 1'
    );

    $categoryQuery->execute([
        'category_id' => $categoryId,
    ]);

    $categoryName =
        $categoryQuery->fetchColumn();

    if ($categoryName === false) {
        return;
    }

    $categoryName =
        (string) $categoryName;

    $alertTitle =
        'Budget alert: ' .
        $categoryName;

    /*
     * Used for cleaning older notification formats.
     */
    $messagePrefix =
        $categoryName .
        ' spending%';


    /* =====================================================
       BUDGET
       ===================================================== */

    $budgetQuery = $db->prepare(
        'SELECT limit_amount
         FROM budgets
         WHERE user_id = :user_id
           AND category_id = :category_id
           AND budget_month = :month
         LIMIT 1'
    );

    $budgetQuery->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
        'month' => $monthStart,
    ]);

    $budget = $budgetQuery->fetch();


    /*
     * No budget anymore:
     * remove active unread alert.
     */
    if (!$budget) {
        $clear = $db->prepare(
            'DELETE FROM notifications
             WHERE user_id = :user_id
               AND type = "budget"
               AND is_read = 0
               AND (
                    title = :new_title
                    OR (
                        title IN (
                            "Budget almost reached",
                            "Budget exceeded"
                        )
                        AND message LIKE :message_prefix
                    )
               )'
        );

        $clear->execute([
            'user_id' => $userId,
            'new_title' => $alertTitle,
            'message_prefix' =>
                $messagePrefix,
        ]);

        return;
    }


    $limit =
        (float) $budget[
            'limit_amount'
        ];

    if ($limit <= 0) {
        return;
    }


    /* =====================================================
       MONTHLY SPENDING
       ===================================================== */

    $spentQuery = $db->prepare(
        'SELECT COALESCE(
            SUM(amount),
            0
         )
         FROM transactions
         WHERE user_id = :user_id
           AND category_id = :category_id
           AND type = "expense"
           AND transaction_date
               BETWEEN :month_start
               AND :month_end'
    );

    $spentQuery->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
    ]);

    $spent =
        (float) $spentQuery
            ->fetchColumn();


    $percent =
        ($spent / $limit) * 100;


    /* =====================================================
       BELOW 80%
       No warning needed
       ===================================================== */

    if ($percent < 80) {
        $clear = $db->prepare(
            'DELETE FROM notifications
             WHERE user_id = :user_id
               AND type = "budget"
               AND is_read = 0
               AND (
                    title = :new_title
                    OR (
                        title IN (
                            "Budget almost reached",
                            "Budget exceeded"
                        )
                        AND message LIKE :message_prefix
                    )
               )'
        );

        $clear->execute([
            'user_id' => $userId,
            'new_title' => $alertTitle,
            'message_prefix' =>
                $messagePrefix,
        ]);

        return;
    }


    /* =====================================================
       HUMAN-FRIENDLY MESSAGE
       ===================================================== */

    if ($percent > 100) {

        /*
         * Example:
         * 150% total usage
         * becomes
         * 50% over budget
         */
        $overPercent =
            $percent - 100;

        $overAmount =
            max(
                0,
                $spent - $limit
            );

        $message = sprintf(
            'Exceeded: %s spending is %.0f%% over your monthly budget (%s over).',
            $categoryName,
            $overPercent,
            '$' . number_format(
                $overAmount,
                2
            )
        );

    } elseif ($percent >= 100) {

        $message = sprintf(
            'Limit reached: %s spending has reached your monthly budget limit.',
            $categoryName
        );

    } else {

        $message = sprintf(
            'Near limit: %s spending has reached %.0f%% of your monthly budget.',
            $categoryName,
            $percent
        );
    }


    /* =====================================================
       CHECK EXISTING ACTIVE ALERT
       ===================================================== */

    $existingQuery = $db->prepare(
        'SELECT id, message
         FROM notifications
         WHERE user_id = :user_id
           AND type = "budget"
           AND is_read = 0
           AND title = :title
           AND created_at >= :month_start
           AND created_at <
               DATE_ADD(
                   :month_end,
                   INTERVAL 1 DAY
               )
         ORDER BY created_at DESC
         LIMIT 1'
    );

    $existingQuery->execute([
        'user_id' => $userId,
        'title' => $alertTitle,
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
    ]);

    $existing =
        $existingQuery->fetch();


    /*
     * An unread notification already exists.
     * Update it so old messages such as
     * "150% of your budget" become
     * "50% over your budget".
     */
    if ($existing) {

        if (
            (string) $existing[
                'message'
            ] !== $message
        ) {
            $update = $db->prepare(
                'UPDATE notifications
                 SET message = :message
                 WHERE id = :id
                   AND user_id = :user_id'
            );

            $update->execute([
                'message' => $message,
                'id' => (int) $existing[
                    'id'
                ],
                'user_id' => $userId,
            ]);
        }

        return;
    }


    /* =====================================================
       CLEAN LEGACY UNREAD ALERTS
       ===================================================== */

    $clearLegacy = $db->prepare(
        'DELETE FROM notifications
         WHERE user_id = :user_id
           AND type = "budget"
           AND is_read = 0
           AND (
                title IN (
                    "Budget almost reached",
                    "Budget exceeded"
                )
                AND message LIKE :message_prefix
           )'
    );

    $clearLegacy->execute([
        'user_id' => $userId,
        'message_prefix' =>
            $messagePrefix,
    ]);


    /* =====================================================
       INSERT NEW ALERT
       ===================================================== */

    $insert = $db->prepare(
        'INSERT INTO notifications
            (
                user_id,
                title,
                message,
                type,
                is_read,
                created_at
            )
         VALUES
            (
                :user_id,
                :title,
                :message,
                "budget",
                0,
                NOW()
            )'
    );

    $insert->execute([
        'user_id' => $userId,
        'title' => $alertTitle,
        'message' => $message,
    ]);
}