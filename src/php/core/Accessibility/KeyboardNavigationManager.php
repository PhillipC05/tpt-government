<?php
/**
 * TPT Government Platform - Keyboard Navigation Manager
 *
 * Specialized manager for keyboard accessibility and navigation
 */

namespace Core\Accessibility;

class KeyboardNavigationManager
{
    /**
     * Keyboard navigation configuration
     */
    private array $config = [
        'enabled' => true,
        'tab_order' => true,
        'focus_management' => true,
        'keyboard_shortcuts' => true,
        'skip_links' => true,
        'focus_indicators' => true,
        'focus_trapping' => false,
        'auto_focus' => true
    ];

    /**
     * Registered keyboard shortcuts
     */
    private array $shortcuts = [];

    /**
     * Focus history for navigation
     */
    private array $focusHistory = [];

    /**
     * Skip links configuration
     */
    private array $skipLinks = [
        ['href' => '#main-content', 'text' => 'Skip to main content'],
        ['href' => '#navigation', 'text' => 'Skip to navigation'],
        ['href' => '#search', 'text' => 'Skip to search']
    ];

    /**
     * Tab order elements
     */
    private array $tabOrder = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->initialize();
    }

    /**
     * Initialize keyboard navigation
     */
    private function initialize(): void
    {
        if ($this->config['keyboard_shortcuts']) {
            $this->setupDefaultShortcuts();
        }

        if ($this->config['skip_links']) {
            $this->setupSkipLinks();
        }

        if ($this->config['focus_management']) {
            $this->setupFocusManagement();
        }
    }

    /**
     * Set up default keyboard shortcuts
     */
    private function setupDefaultShortcuts(): void
    {
        $this->registerShortcut('h', 'Show help', 'global', function() {
            $this->showKeyboardHelp();
        });

        $this->registerShortcut('1', 'Go to home', 'global', function() {
            $this->navigateTo('/');
        });

        $this->registerShortcut('s', 'Focus search', 'global', function() {
            $this->focusElement('#search-input');
        });

        $this->registerShortcut('n', 'Focus navigation', 'global', function() {
            $this->focusElement('#main-navigation');
        });

        // Arrow key navigation for menus
        $this->registerShortcut('ArrowUp', 'Previous menu item', 'menu', function() {
            $this->navigateMenu('up');
        });

        $this->registerShortcut('ArrowDown', 'Next menu item', 'menu', function() {
            $this->navigateMenu('down');
        });

        $this->registerShortcut('Enter', 'Activate menu item', 'menu', function() {
            $this->activateMenuItem();
        });

        $this->registerShortcut('Escape', 'Close menu', 'menu', function() {
            $this->closeMenu();
        });
    }

    /**
     * Register a keyboard shortcut
     */
    public function registerShortcut(string $key, string $description, string $context, callable $handler): void
    {
        $this->shortcuts[] = [
            'key' => $key,
            'description' => $description,
            'context' => $context,
            'handler' => $handler,
            'enabled' => true
        ];
    }

    /**
     * Handle keyboard event
     */
    public function handleKeyEvent(string $key, array $modifiers = [], string $context = 'global'): bool
    {
        // Find matching shortcut
        foreach ($this->shortcuts as $shortcut) {
            if ($shortcut['key'] === $key &&
                $shortcut['context'] === $context &&
                $shortcut['enabled']) {

                // Check modifiers if specified
                if (!empty($modifiers) && !$this->checkModifiers($shortcut, $modifiers)) {
                    continue;
                }

                // Execute handler
                call_user_func($shortcut['handler']);
                return true;
            }
        }

        return false;
    }

    /**
     * Set up tab order for elements
     */
    public function setTabOrder(array $elements): void
    {
        $this->tabOrder = $elements;
        $this->updateTabIndexes();
    }

    /**
     * Add element to tab order
     */
    public function addToTabOrder(string $selector, int $priority = 0): void
    {
        $this->tabOrder[] = [
            'selector' => $selector,
            'priority' => $priority
        ];

        // Sort by priority
        usort($this->tabOrder, fn($a, $b) => $a['priority'] <=> $b['priority']);
        $this->updateTabIndexes();
    }

    /**
     * Remove element from tab order
     */
    public function removeFromTabOrder(string $selector): void
    {
        $this->tabOrder = array_filter($this->tabOrder, fn($item) => $item['selector'] !== $selector);
        $this->updateTabIndexes();
    }

    /**
     * Focus next element in tab order
     */
    public function focusNext(): void
    {
        $currentFocus = $this->getCurrentFocus();
        $nextElement = $this->findNextElement($currentFocus);

        if ($nextElement) {
            $this->focusElement($nextElement['selector']);
        }
    }

    /**
     * Focus previous element in tab order
     */
    public function focusPrevious(): void
    {
        $currentFocus = $this->getCurrentFocus();
        $previousElement = $this->findPreviousElement($currentFocus);

        if ($previousElement) {
            $this->focusElement($previousElement['selector']);
        }
    }

    /**
     * Set focus to specific element
     */
    public function focusElement(string $selector): void
    {
        // Track focus history
        $this->focusHistory[] = [
            'selector' => $selector,
            'timestamp' => time()
        ];

        // Limit history size
        if (count($this->focusHistory) > 10) {
            array_shift($this->focusHistory);
        }

        // In a real implementation, this would inject JavaScript to focus the element
        $this->injectFocusScript($selector);
    }

    /**
     * Trap focus within an element
     */
    public function trapFocus(string $containerSelector): void
    {
        if (!$this->config['focus_trapping']) {
            return;
        }

        $this->config['focus_trap_container'] = $containerSelector;
        $this->setupFocusTrap();
    }

    /**
     * Release focus trap
     */
    public function releaseFocusTrap(): void
    {
        unset($this->config['focus_trap_container']);
        $this->removeFocusTrap();
    }

    /**
     * Set up skip links
     */
    public function setupSkipLinks(): void
    {
        // Skip links are configured in the property
        // In a real implementation, this would inject the skip links into the page
    }

    /**
     * Add custom skip link
     */
    public function addSkipLink(string $href, string $text): void
    {
        $this->skipLinks[] = [
            'href' => $href,
            'text' => $text
        ];
    }

    /**
     * Get skip links
     */
    public function getSkipLinks(): array
    {
        return $this->skipLinks;
    }

    /**
     * Enable focus indicators
     */
    public function enableFocusIndicators(): void
    {
        $this->config['focus_indicators'] = true;
        $this->injectFocusIndicatorStyles();
    }

    /**
     * Disable focus indicators
     */
    public function disableFocusIndicators(): void
    {
        $this->config['focus_indicators'] = false;
        $this->removeFocusIndicatorStyles();
    }

    /**
     * Show keyboard navigation help
     */
    public function showKeyboardHelp(): void
    {
        $helpContent = $this->generateHelpContent();
        // In a real implementation, this would display a modal or overlay with help
    }

    /**
     * Get keyboard navigation status
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->config['enabled'],
            'tab_order_enabled' => $this->config['tab_order'],
            'focus_management_enabled' => $this->config['focus_management'],
            'keyboard_shortcuts_enabled' => $this->config['keyboard_shortcuts'],
            'skip_links_enabled' => $this->config['skip_links'],
            'focus_indicators_enabled' => $this->config['focus_indicators'],
            'total_shortcuts' => count($this->shortcuts),
            'total_tab_elements' => count($this->tabOrder),
            'focus_history_size' => count($this->focusHistory)
        ];
    }

    /**
     * Get registered shortcuts
     */
    public function getShortcuts(string $context = null): array
    {
        if ($context === null) {
            return $this->shortcuts;
        }

        return array_filter($this->shortcuts, fn($shortcut) => $shortcut['context'] === $context);
    }

    /**
     * Clear focus history
     */
    public function clearFocusHistory(): void
    {
        $this->focusHistory = [];
    }

    /**
     * Get focus history
     */
    public function getFocusHistory(int $limit = 10): array
    {
        return array_slice(array_reverse($this->focusHistory), 0, $limit);
    }

    // Helper methods

    private function checkModifiers(array $shortcut, array $modifiers): bool
    {
        // Check if shortcut has modifier requirements
        return !isset($shortcut['modifiers']) ||
               empty(array_diff($shortcut['modifiers'], $modifiers));
    }

    private function getCurrentFocus(): ?string
    {
        // In a real implementation, this would get the currently focused element
        return null;
    }

    private function findNextElement(?string $current): ?array
    {
        if (empty($this->tabOrder)) {
            return null;
        }

        $currentIndex = $current ? $this->findElementIndex($current) : -1;
        $nextIndex = ($currentIndex + 1) % count($this->tabOrder);

        return $this->tabOrder[$nextIndex];
    }

    private function findPreviousElement(?string $current): ?array
    {
        if (empty($this->tabOrder)) {
            return null;
        }

        $currentIndex = $current ? $this->findElementIndex($current) : 0;
        $previousIndex = $currentIndex > 0 ? $currentIndex - 1 : count($this->tabOrder) - 1;

        return $this->tabOrder[$previousIndex];
    }

    private function findElementIndex(string $selector): int
    {
        foreach ($this->tabOrder as $index => $element) {
            if ($element['selector'] === $selector) {
                return $index;
            }
        }
        return -1;
    }

    private function updateTabIndexes(): void
    {
        // In a real implementation, this would update tabindex attributes
    }

    private function injectFocusScript(string $selector): void
    {
        // Inject JavaScript to focus element
    }

    private function setupFocusManagement(): void
    {
        // Set up focus event listeners
    }

    private function setupFocusTrap(): void
    {
        // Set up focus trapping within container
    }

    private function removeFocusTrap(): void
    {
        // Remove focus trap
    }

    private function injectFocusIndicatorStyles(): void
    {
        // Inject CSS for focus indicators
    }

    private function removeFocusIndicatorStyles(): void
    {
        // Remove focus indicator styles
    }

    private function navigateMenu(string $direction): void
    {
        // Navigate menu items
    }

    private function activateMenuItem(): void
    {
        // Activate current menu item
    }

    private function closeMenu(): void
    {
        // Close current menu
    }

    private function navigateTo(string $url): void
    {
        // Navigate to URL
    }

    private function generateHelpContent(): string
    {
        $content = "<h2>Keyboard Navigation Help</h2>";
        $content .= "<h3>Global Shortcuts:</h3><ul>";

        foreach ($this->getShortcuts('global') as $shortcut) {
            $content .= "<li><kbd>{$shortcut['key']}</kbd>: {$shortcut['description']}</li>";
        }

        $content .= "</ul><h3>Menu Navigation:</h3><ul>";
        $content .= "<li><kbd>↑</kbd> <kbd>↓</kbd>: Navigate menu items</li>";
        $content .= "<li><kbd>Enter</kbd>: Activate menu item</li>";
        $content .= "<li><kbd>Escape</kbd>: Close menu</li>";
        $content .= "</ul>";

        return $content;
    }
}
