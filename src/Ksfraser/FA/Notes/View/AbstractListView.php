<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\View;

/**
 * Base class for notes list views.
 *
 * Holds the collaborators every list view needs — the options provider that
 * asks other modules for dropdown data, and the logged-in user id — so
 * subclasses render instead of wiring. Mirrors the Calendar list-view base so
 * the two modules read alike.
 *
 * @BABOK Related: FR-NT-001-005
 * @since 1.0.0
 */
abstract class AbstractListView
{
    /** @var Entity\EntityOptionsProvider */
    protected $options;

    /** @var string */
    protected $userId;

    /** @var string */
    protected $userName;

    /** @var NoteFilterBar */
    protected $filterBar;

    /**
     * @param Entity\EntityOptionsProvider $options  Dropdown data, from responders
     * @param string                       $userId   Logged-in user id
     * @param string                       $userName Logged-in user's display name
     * @since 1.0.0
     */
    public function __construct(
        \ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider $options,
        string $userId = '',
        string $userName = ''
    ) {
        $this->options = $options;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->filterBar = new NoteFilterBar($options);
    }

    /**
     * @return void
     * @since 1.0.0
     */
    abstract public function render(): void;

    /**
     * Open the summary table with its column headings.
     *
     * @param string[] $headings Column headings
     * @param string   $css      Extra CSS for the table
     * @return void
     * @since 1.0.0
     */
    protected function renderTableStart(array $headings, string $css = ''): void
    {
        $style = 'width:100%;max-width:1100px;' . $css;

        echo '<table class="selection" style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo "<thead><tr>\n";

        foreach ($headings as $heading) {
            echo '<th style="text-align:left;">' . $heading . "</th>\n";
        }

        echo "</tr></thead>\n<tbody>\n";
    }

    /**
     * Close the summary table.
     *
     * @return void
     * @since 1.0.0
     */
    protected function renderTableEnd(): void
    {
        echo "</tbody>\n</table>\n";
    }

    /**
     * One row of the summary table.
     *
     * @param string[] $cells Already-escaped text or safe markup
     * @return void
     * @since 1.0.0
     */
    protected function renderRow(array $cells): void
    {
        echo "<tr>\n";

        foreach ($cells as $cell) {
            echo '<td>' . $cell . "</td>\n";
        }

        echo "</tr>\n";
    }

    /**
     * Escape a value for display, optionally truncated.
     *
     * @param mixed $value   Raw value
     * @param int   $maxLength Maximum characters; 0 for no truncation
     * @return string
     * @since 1.0.0
     */
    protected function safeStr($value, int $maxLength = 0): string
    {
        $text = (string) ($value ?? '');

        if ($maxLength > 0 && strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 1) . '…';
        }

        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format a stored datetime in the user's date format.
     *
     * @param string|null $value SQL datetime
     * @return string
     * @since 1.0.0
     */
    protected function formatDateTime($value): string
    {
        if ($value === null || $value === '' || strpos((string) $value, '0000-00-00') === 0) {
            return '';
        }

        if (function_exists('sql2date')) {
            $formatted = sql2date((string) $value);
            if ($formatted !== false && $formatted !== '') {
                return $formatted;
            }
        }

        return $this->safeStr($value);
    }

    /**
     * A link that carries the current filter, so paging or editing and coming
     * back does not lose the user's filter.
     *
     * @param string $path  Target script
     * @param array  $extra Extra query parameters
     * @return string
     * @since 1.0.0
     */
    protected function url(string $path, array $extra = array()): string
    {
        return $path . '?' . http_build_query(array_merge($extra, array('t' => time())));
    }
}