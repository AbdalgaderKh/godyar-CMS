<?php
declare(strict_types=1);

/**
 * كلاس بسيط للتقسيم (Pagination)
 */

class Pagination
{
    public int $total;
    public int $perPage;
    public int $currentPage;
    public string $param;

    public function __construct(int $total, int $perPage = 20, int $currentPage = 1, string $param = 'page')
    {
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->param       = $param;
    }

    public function pages(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }
        return (int)ceil($this->total / $this->perPage);
    }

    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function limitClause(): string
    {
        return ' LIMIT ' . $this->offset() . ', ' . $this->perPage . ' ';
    }

    public function render(string $baseUrl): string
    {
        $pages = $this->pages();
        if ($pages <= 1) {
            return '';
        }

        $html = '<nav class="pagination"><ul class="pagination-list">';

        for ($i = 1; $i <= $pages; $i++) {
            $active = $i === $this->currentPage ? ' active' : '';
            $sep    = (strpos($baseUrl, '?') === false) ? '?' : '&';
            $url    = htmlspecialchars($baseUrl . $sep . $this->param . '=' . $i, ENT_QUOTES, 'UTF-8');

            $html .= '<li class="page-item' . $active . '">';
            $html .= '<a class="page-link" href="' . $url . '">' . $i . '</a>';
            $html .= '</li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }
}
