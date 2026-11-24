<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function setFlash(string $type, string $text): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'text' => $text
    ];
}

function renderFlash(): void {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $isError = ($flash['type'] === 'error');
        $class = $isError ? 'alert-danger' : 'alert-success';
        $style = $isError ? 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : '';
        echo '<div class="alert-message ' . $class . '" style="' . $style . '">' . htmlspecialchars($flash['text']) . '</div>';
    }
}

function renderPagination(int $currentPage, int $totalPages, array $queryParams = []): void {
    if ($totalPages <= 1) {
        return;
    }

    unset($queryParams['page'], $queryParams['edit_id']);

    $buildUrl = function (int $page) use ($queryParams): string {
        $params = array_merge($queryParams, ['page' => $page]);
        return '?' . http_build_query($params);
    };

    echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px;">';
    echo '<span style="font-size: 13px; color: #837a9c;">Page <strong>' . $currentPage . '</strong> of <strong>' . $totalPages . '</strong></span>';
    echo '<div style="display: flex; gap: 6px; flex-wrap: wrap;">';

    if ($currentPage > 1) {
        echo '<a href="' . htmlspecialchars($buildUrl($currentPage - 1)) . '" class="btn-secondary btn-sm">&laquo; Prev</a>';
    }

    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === $currentPage) {
            echo '<span class="btn-primary btn-sm" style="cursor: default;">' . $p . '</span>';
        } else {
            echo '<a href="' . htmlspecialchars($buildUrl($p)) . '" class="btn-secondary btn-sm">' . $p . '</a>';
        }
    }

    if ($currentPage < $totalPages) {
        echo '<a href="' . htmlspecialchars($buildUrl($currentPage + 1)) . '" class="btn-secondary btn-sm">Next &raquo;</a>';
    }

    echo '</div></div>';
}
