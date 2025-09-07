<?php
/**
 * TPT Government Platform - Internationalization & Localization System
 *
 * Comprehensive i18n system supporting 50+ languages, RTL languages,
 * cultural formatting, and dynamic content translation
 */

class Internationalization
{
    private array $config;
    private array $translations;
    private array $localeData;
    private string $currentLocale;
    private string $fallbackLocale;
    private array $supportedLocales;
    private array $pluralRules;
    private array $dateFormats;
    private array $numberFormats;
    private array $currencyFormats;

    /**
     * Supported locales configuration
     */
    private array $localeConfig = [
        'supported_locales' => [
            // European Languages
            'en' => ['name' => 'English', 'native' => 'English', 'direction' => 'ltr', 'flag' => '🇺🇸'],
            'es' => ['name' => 'Spanish', 'native' => 'Español', 'direction' => 'ltr', 'flag' => '🇪🇸'],
            'fr' => ['name' => 'French', 'native' => 'Français', 'direction' => 'ltr', 'flag' => '🇫🇷'],
            'de' => ['name' => 'German', 'native' => 'Deutsch', 'direction' => 'ltr', 'flag' => '🇩🇪'],
            'it' => ['name' => 'Italian', 'native' => 'Italiano', 'direction' => 'ltr', 'flag' => '🇮🇹'],
            'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'direction' => 'ltr', 'flag' => '🇵🇹'],
            'ru' => ['name' => 'Russian', 'native' => 'Русский', 'direction' => 'ltr', 'flag' => '🇷🇺'],
            'nl' => ['name' => 'Dutch', 'native' => 'Nederlands', 'direction' => 'ltr', 'flag' => '🇳🇱'],
            'sv' => ['name' => 'Swedish', 'native' => 'Svenska', 'direction' => 'ltr', 'flag' => '🇸🇪'],
            'da' => ['name' => 'Danish', 'native' => 'Dansk', 'direction' => 'ltr', 'flag' => '🇩🇰'],
            'no' => ['name' => 'Norwegian', 'native' => 'Norsk', 'direction' => 'ltr', 'flag' => '🇳🇴'],
            'fi' => ['name' => 'Finnish', 'native' => 'Suomi', 'direction' => 'ltr', 'flag' => '🇫🇮'],
            'pl' => ['name' => 'Polish', 'native' => 'Polski', 'direction' => 'ltr', 'flag' => '🇵🇱'],
            'cs' => ['name' => 'Czech', 'native' => 'Čeština', 'direction' => 'ltr', 'flag' => '🇨🇿'],
            'sk' => ['name' => 'Slovak', 'native' => 'Slovenčina', 'direction' => 'ltr', 'flag' => '🇸🇰'],
            'hu' => ['name' => 'Hungarian', 'native' => 'Magyar', 'direction' => 'ltr', 'flag' => '🇭🇺'],
            'ro' => ['name' => 'Romanian', 'native' => 'Română', 'direction' => 'ltr', 'flag' => '🇷🇴'],
            'bg' => ['name' => 'Bulgarian', 'native' => 'Български', 'direction' => 'ltr', 'flag' => '🇧🇬'],
            'hr' => ['name' => 'Croatian', 'native' => 'Hrvatski', 'direction' => 'ltr', 'flag' => '🇭🇷'],
            'sl' => ['name' => 'Slovenian', 'native' => 'Slovenščina', 'direction' => 'ltr', 'flag' => '🇸🇮'],

            // Asian Languages
            'zh' => ['name' => 'Chinese', 'native' => '中文', 'direction' => 'ltr', 'flag' => '🇨🇳'],
            'ja' => ['name' => 'Japanese', 'native' => '日本語', 'direction' => 'ltr', 'flag' => '🇯🇵'],
            'ko' => ['name' => 'Korean', 'native' => '한국어', 'direction' => 'ltr', 'flag' => '🇰🇷'],
            'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'direction' => 'ltr', 'flag' => '🇮🇳'],
            'th' => ['name' => 'Thai', 'native' => 'ไทย', 'direction' => 'ltr', 'flag' => '🇹🇭'],
            'vi' => ['name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'direction' => 'ltr', 'flag' => '🇻🇳'],
            'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'direction' => 'ltr', 'flag' => '🇮🇩'],
            'ms' => ['name' => 'Malay', 'native' => 'Bahasa Melayu', 'direction' => 'ltr', 'flag' => '🇲🇾'],
            'tl' => ['name' => 'Filipino', 'native' => 'Filipino', 'direction' => 'ltr', 'flag' => '🇵🇭'],

            // Middle Eastern Languages
            'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'direction' => 'rtl', 'flag' => '🇸🇦'],
            'he' => ['name' => 'Hebrew', 'native' => 'עברית', 'direction' => 'rtl', 'flag' => '🇮🇱'],
            'fa' => ['name' => 'Persian', 'native' => 'فارسی', 'direction' => 'rtl', 'flag' => '🇮🇷'],
            'ur' => ['name' => 'Urdu', 'native' => 'اردو', 'direction' => 'rtl', 'flag' => '🇵🇰'],
            'tr' => ['name' => 'Turkish', 'native' => 'Türkçe', 'direction' => 'ltr', 'flag' => '🇹🇷'],

            // African Languages
            'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili', 'direction' => 'ltr', 'flag' => '🇹🇿'],
            'am' => ['name' => 'Amharic', 'native' => 'አማርኛ', 'direction' => 'ltr', 'flag' => '🇪🇹'],
            'ha' => ['name' => 'Hausa', 'native' => 'Hausa', 'direction' => 'ltr', 'flag' => '🇳🇬'],
            'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'direction' => 'ltr', 'flag' => '🇳🇬'],
            'zu' => ['name' => 'Zulu', 'native' => 'isiZulu', 'direction' => 'ltr', 'flag' => '🇿🇦'],

            // American Languages
            'pt-BR' => ['name' => 'Brazilian Portuguese', 'native' => 'Português Brasileiro', 'direction' => 'ltr', 'flag' => '🇧🇷'],
            'es-MX' => ['name' => 'Mexican Spanish', 'native' => 'Español Mexicano', 'direction' => 'ltr', 'flag' => '🇲🇽'],
            'es-AR' => ['name' => 'Argentine Spanish', 'native' => 'Español Argentino', 'direction' => 'ltr', 'flag' => '🇦🇷'],
            'fr-CA' => ['name' => 'Canadian French', 'native' => 'Français Canadien', 'direction' => 'ltr', 'flag' => '🇨🇦'],

            // Other Languages
            'af' => ['name' => 'Afrikaans', 'native' => 'Afrikaans', 'direction' => 'ltr', 'flag' => '🇿🇦'],
            'sq' => ['name' => 'Albanian', 'native' => 'Shqip', 'direction' => 'ltr', 'flag' => '🇦🇱'],
            'hy' => ['name' => 'Armenian', 'native' => 'Հայերեն', 'direction' => 'ltr', 'flag' => '🇦🇲'],
            'eu' => ['name' => 'Basque', 'native' => 'Euskera', 'direction' => 'ltr', 'flag' => '🇪🇸'],
            'be' => ['name' => 'Belarusian', 'native' => 'Беларуская', 'direction' => 'ltr', 'flag' => '🇧🇾'],
            'bn' => ['name' => 'Bengali', 'native' => 'বাংলা', 'direction' => 'ltr', 'flag' => '🇧🇩'],
            'bs' => ['name' => 'Bosnian', 'native' => 'Bosanski', 'direction' => 'ltr', 'flag' => '🇧🇦'],
            'ca' => ['name' => 'Catalan', 'native' => 'Català', 'direction' => 'ltr', 'flag' => '🇪🇸'],
            'et' => ['name' => 'Estonian', 'native' => 'Eesti', 'direction' => 'ltr', 'flag' => '🇪🇪'],
            'gl' => ['name' => 'Galician', 'native' => 'Galego', 'direction' => 'ltr', 'flag' => '🇪🇸'],
            'ka' => ['name' => 'Georgian', 'native' => 'ქართული', 'direction' => 'ltr', 'flag' => '🇬🇪'],
            'el' => ['name' => 'Greek', 'native' => 'Ελληνικά', 'direction' => 'ltr', 'flag' => '🇬🇷'],
            'is' => ['name' => 'Icelandic', 'native' => 'Íslenska', 'direction' => 'ltr', 'flag' => '🇮🇸'],
            'ga' => ['name' => 'Irish', 'native' => 'Gaeilge', 'direction' => 'ltr', 'flag' => '🇮🇪'],
            'kk' => ['name' => 'Kazakh', 'native' => 'Қазақша', 'direction' => 'ltr', 'flag' => '🇰🇿'],
            'lv' => ['name' => 'Latvian', 'native' => 'Latviešu', 'direction' => 'ltr', 'flag' => '🇱🇻'],
            'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių', 'direction' => 'ltr', 'flag' => '🇱🇹'],
            'mk' => ['name' => 'Macedonian', 'native' => 'Македонски', 'direction' => 'ltr', 'flag' => '🇲🇰'],
            'mn' => ['name' => 'Mongolian', 'native' => 'Монгол', 'direction' => 'ltr', 'flag' => '🇲🇳'],
            'ne' => ['name' => 'Nepali', 'native' => 'नेपाली', 'direction' => 'ltr', 'flag' => '🇳🇵'],
            'sr' => ['name' => 'Serbian', 'native' => 'Српски', 'direction' => 'ltr', 'flag' => '🇷🇸'],
            'si' => ['name' => 'Sinhala', 'native' => 'සිංහල', 'direction' => 'ltr', 'flag' => '🇱🇰'],
            'ta' => ['name' => 'Tamil', 'native' => 'தமிழ்', 'direction' => 'ltr', 'flag' => '🇱🇰'],
            'te' => ['name' => 'Telugu', 'native' => 'తెలుగు', 'direction' => 'ltr', 'flag' => '🇮🇳'],
            'uk' => ['name' => 'Ukrainian', 'native' => 'Українська', 'direction' => 'ltr', 'flag' => '🇺🇦'],
            'uz' => ['name' => 'Uzbek', 'native' => 'Oʻzbekcha', 'direction' => 'ltr', 'flag' => '🇺🇿']
        ],
        'fallback_locale' => 'en',
        'default_locale' => 'en',
        'auto_detect' => true,
        'url_prefix' => true,
        'cache_translations' => true,
        'translation_sources' => ['database', 'files', 'api']
    ];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->localeConfig, $config);
        $this->supportedLocales = $this->config['supported_locales'];
        $this->fallbackLocale = $this->config['fallback_locale'];
        $this->currentLocale = $this->detectLocale();

        $this->initializePluralRules();
        $this->initializeDateFormats();
        $this->initializeNumberFormats();
        $this->initializeCurrencyFormats();
        $this->loadTranslations();
    }

    /**
     * Detect user locale
     */
    private function detectLocale(): string
    {
        // Check URL prefix first
        if ($this->config['url_prefix']) {
            $urlLocale = $this->getLocaleFromUrl();
            if ($urlLocale && isset($this->supportedLocales[$urlLocale])) {
                return $urlLocale;
            }
        }

        // Check user session/cookie
        $sessionLocale = $this->getLocaleFromSession();
        if ($sessionLocale && isset($this->supportedLocales[$sessionLocale])) {
            return $sessionLocale;
        }

        // Auto-detect from browser
        if ($this->config['auto_detect']) {
            $browserLocale = $this->getLocaleFromBrowser();
            if ($browserLocale && isset($this->supportedLocales[$browserLocale])) {
                return $browserLocale;
            }
        }

        // Return default
        return $this->config['default_locale'];
    }

    /**
     * Get locale from URL
     */
    private function getLocaleFromUrl(): ?string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $pathParts = explode('/', trim($requestUri, '/'));

        if (!empty($pathParts[0]) && strlen($pathParts[0]) <= 5) {
            return $pathParts[0];
        }

        return null;
    }

    /**
     * Get locale from session
     */
    private function getLocaleFromSession(): ?string
    {
        return $_SESSION['locale'] ?? $_COOKIE['locale'] ?? null;
    }

    /**
     * Get locale from browser Accept-Language header
     */
    private function getLocaleFromBrowser(): ?string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if (empty($acceptLanguage)) {
            return null;
        }

        // Parse Accept-Language header
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            $locale = trim(explode(';', $language)[0]);

            // Check exact match
            if (isset($this->supportedLocales[$locale])) {
                return $locale;
            }

            // Check language prefix (e.g., 'en' for 'en-US')
            $languagePrefix = explode('-', $locale)[0];
            if (isset($this->supportedLocales[$languagePrefix])) {
                return $languagePrefix;
            }
        }

        return null;
    }

    /**
     * Initialize plural rules for different languages
     */
    private function initializePluralRules(): void
    {
        $this->pluralRules = [
            'en' => function($n) {
                return $n === 1 ? 'one' : 'other';
            },
            'es' => function($n) {
                return $n === 1 ? 'one' : 'other';
            },
            'fr' => function($n) {
                return $n === 1 ? 'one' : 'other';
            },
            'de' => function($n) {
                return $n === 1 ? 'one' : 'other';
            },
            'ru' => function($n) {
                if ($n % 10 === 1 && $n % 100 !== 11) return 'one';
                if ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) return 'few';
                return 'many';
            },
            'ar' => function($n) {
                if ($n === 0) return 'zero';
                if ($n === 1) return 'one';
                if ($n === 2) return 'two';
                if ($n % 100 >= 3 && $n % 100 <= 10) return 'few';
                if ($n % 100 >= 11 && $n % 100 <= 99) return 'many';
                return 'other';
            },
            'zh' => function($n) {
                return 'other';
            },
            'ja' => function($n) {
                return 'other';
            },
            'ko' => function($n) {
                return 'other';
            }
        ];
    }

    /**
     * Initialize date formats for different locales
     */
    private function initializeDateFormats(): void
    {
        $this->dateFormats = [
            'en' => ['short' => 'M/d/Y', 'medium' => 'M d, Y', 'long' => 'F d, Y', 'full' => 'l, F d, Y'],
            'es' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'd F Y', 'full' => 'l d F Y'],
            'fr' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'd F Y', 'full' => 'l d F Y'],
            'de' => ['short' => 'd.m.Y', 'medium' => 'd.m.Y', 'long' => 'd. F Y', 'full' => 'l, d. F Y'],
            'ja' => ['short' => 'Y/m/d', 'medium' => 'Y年m月d日', 'long' => 'Y年m月d日', 'full' => 'Y年m月d日 (D)'],
            'zh' => ['short' => 'Y/m/d', 'medium' => 'Y年m月d日', 'long' => 'Y年m月d日', 'full' => 'Y年m月d日 D'],
            'ar' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'd F Y', 'full' => 'l، d F Y'],
            'hi' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'd F Y', 'full' => 'd F Y, l']
        ];
    }

    /**
     * Initialize number formats for different locales
     */
    private function initializeNumberFormats(): void
    {
        $this->numberFormats = [
            'en' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'es' => ['decimal' => ',', 'thousands' => '.', 'precision' => 2],
            'fr' => ['decimal' => ',', 'thousands' => ' ', 'precision' => 2],
            'de' => ['decimal' => ',', 'thousands' => '.', 'precision' => 2],
            'ja' => ['decimal' => '.', 'thousands' => ',', 'precision' => 0],
            'zh' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'ar' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'hi' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'pt-BR' => ['decimal' => ',', 'thousands' => '.', 'precision' => 2]
        ];
    }

    /**
     * Initialize currency formats for different locales
     */
    private function initializeCurrencyFormats(): void
    {
        $this->currencyFormats = [
            'en' => ['symbol' => '$', 'position' => 'before', 'code' => 'USD'],
            'es' => ['symbol' => '€', 'position' => 'before', 'code' => 'EUR'],
            'fr' => ['symbol' => '€', 'position' => 'before', 'code' => 'EUR'],
            'de' => ['symbol' => '€', 'position' => 'before', 'code' => 'EUR'],
            'ja' => ['symbol' => '¥', 'position' => 'before', 'code' => 'JPY'],
            'zh' => ['symbol' => '¥', 'position' => 'before', 'code' => 'CNY'],
            'ar' => ['symbol' => 'ر.س', 'position' => 'before', 'code' => 'SAR'],
            'hi' => ['symbol' => '₹', 'position' => 'before', 'code' => 'INR'],
            'pt-BR' => ['symbol' => 'R$', 'position' => 'before', 'code' => 'BRL']
        ];
    }

    /**
     * Load translations for current locale
     */
    private function loadTranslations(): void
    {
        $this->translations = [];

        // Load from different sources
        foreach ($this->config['translation_sources'] as $source) {
            switch ($source) {
                case 'database':
                    $this->loadTranslationsFromDatabase();
                    break;
                case 'files':
                    $this->loadTranslationsFromFiles();
                    break;
                case 'api':
                    $this->loadTranslationsFromApi();
                    break;
            }
        }
    }

    /**
     * Load translations from database
     */
    private function loadTranslationsFromDatabase(): void
    {
        // This would load translations from database
        // For now, we'll use placeholder data
        $this->translations = array_merge($this->translations, [
            'welcome' => 'Welcome',
            'login' => 'Login',
            'logout' => 'Logout',
            'dashboard' => 'Dashboard',
            'settings' => 'Settings'
        ]);
    }

    /**
     * Load translations from files
     */
    private function loadTranslationsFromFiles(): void
    {
        $translationFile = __DIR__ . "/../../translations/{$this->currentLocale}.php";

        if (file_exists($translationFile)) {
            $fileTranslations = include $translationFile;
            $this->translations = array_merge($this->translations, $fileTranslations);
        }
    }

    /**
     * Load translations from API
     */
    private function loadTranslationsFromApi(): void
    {
        // This would load translations from external API
        // For now, we'll skip this
    }

    /**
     * Get current locale
     */
    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Set current locale
     */
    public function setLocale(string $locale): bool
    {
        if (!isset($this->supportedLocales[$locale])) {
            return false;
        }

        $this->currentLocale = $locale;

        // Save to session
        $_SESSION['locale'] = $locale;

        // Reload translations
        $this->loadTranslations();

        return true;
    }

    /**
     * Get supported locales
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Check if locale is RTL
     */
    public function isRtl(string $locale = null): bool
    {
        $locale = $locale ?? $this->currentLocale;
        return isset($this->supportedLocales[$locale]) &&
               $this->supportedLocales[$locale]['direction'] === 'rtl';
    }

    /**
     * Translate a key
     */
    public function translate(string $key, array $params = [], string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;

        // Try current locale first
        if (isset($this->translations[$locale][$key])) {
            $translation = $this->translations[$locale][$key];
        }
        // Try fallback locale
        elseif (isset($this->translations[$this->fallbackLocale][$key])) {
            $translation = $this->translations[$this->fallbackLocale][$key];
        }
        // Return key if not found
        else {
            $translation = $key;
        }

        // Replace parameters
        return $this->replaceParameters($translation, $params);
    }

    /**
     * Translate with pluralization
     */
    public function translatePlural(string $key, int $count, array $params = [], string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;

        // Get plural form
        $pluralForm = $this->getPluralForm($count, $locale);

        // Try to find pluralized key
        $pluralKey = $key . '.' . $pluralForm;

        if (isset($this->translations[$locale][$pluralKey])) {
            $translation = $this->translations[$locale][$pluralKey];
        } elseif (isset($this->translations[$this->fallbackLocale][$pluralKey])) {
            $translation = $this->translations[$this->fallbackLocale][$pluralKey];
        } else {
            // Fall back to singular/plural
            $translation = $count === 1 ? $this->translate($key . '.one', $params, $locale) :
                                         $this->translate($key . '.other', $params, $locale);
        }

        // Replace count parameter
        $params['count'] = $count;

        return $this->replaceParameters($translation, $params);
    }

    /**
     * Get plural form for locale
     */
    private function getPluralForm(int $count, string $locale): string
    {
        if (isset($this->pluralRules[$locale])) {
            return $this->pluralRules[$locale]($count);
        }

        // Default to English plural rules
        return $this->pluralRules['en']($count);
    }

    /**
     * Replace parameters in translation
     */
    private function replaceParameters(string $translation, array $params): string
    {
        foreach ($params as $key => $value) {
            $translation = str_replace(":{$key}", $value, $translation);
            $translation = str_replace("%{$key}%", $value, $translation);
        }

        return $translation;
    }

    /**
     * Format date according to locale
     */
    public function formatDate(DateTime $date, string $format = 'medium', string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->dateFormats[$locale])) {
            $locale = $this->fallbackLocale;
        }

        $formatString = $this->dateFormats[$locale][$format] ?? $this->dateFormats[$locale]['medium'];

        return $date->format($formatString);
    }

    /**
     * Format number according to locale
     */
    public function formatNumber(float $number, int $decimals = null, string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->numberFormats[$locale])) {
            $locale = $this->fallbackLocale;
        }

        $format = $this->numberFormats[$locale];
        $decimals = $decimals ?? $format['precision'];

        return number_format(
            $number,
            $decimals,
            $format['decimal'],
            $format['thousands']
        );
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency(float $amount, string $currency = null, string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->currencyFormats[$locale])) {
            $locale = $this->fallbackLocale;
        }

        $currencyFormat = $this->currencyFormats[$locale];
        $currency = $currency ?? $currencyFormat['code'];

        $formattedAmount = $this->formatNumber($amount, 2, $locale);

        if ($currencyFormat['position'] === 'before') {
            return $currencyFormat['symbol'] . $formattedAmount;
        } else {
            return $formattedAmount . ' ' . $currencyFormat['symbol'];
        }
    }

    /**
     * Get locale information
     */
    public function getLocaleInfo(string $locale = null): array
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->supportedLocales[$locale])) {
            return [];
        }

        return $this->supportedLocales[$locale];
    }

    /**
     * Add translation
     */
    public function addTranslation(string $key, string $translation, string $locale = null): void
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->translations[$locale])) {
            $this->translations[$locale] = [];
        }

        $this->translations[$locale][$key] = $translation;
    }

    /**
     * Add multiple translations
     */
    public function addTranslations(array $translations, string $locale = null): void
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->translations[$locale])) {
            $this->translations[$locale] = [];
        }

        $this->translations[$locale] = array_merge($this->translations[$locale], $translations);
    }

    /**
     * Get all translations for a locale
     */
    public function getTranslations(string $locale = null): array
    {
        $locale = $locale ?? $this->currentLocale;
        return $this->translations[$locale] ?? [];
    }

    /**
     * Export translations
     */
    public function exportTranslations(string $locale = null, string $format = 'php'): string
    {
        $locale = $locale ?? $this->currentLocale;
        $translations = $this->getTranslations($locale);

        switch ($format) {
            case 'php':
                return "<?php\nreturn " . var_export($translations, true) . ";\n";
            case 'json':
                return json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            case 'yaml':
                // Would require YAML extension
                return yaml_emit($translations);
            default:
                return serialize($translations);
        }
    }

    /**
     * Import translations
     */
    public function importTranslations(string $data, string $format = 'php', string $locale = null): bool
    {
        $locale = $locale ?? $this->currentLocale;

        switch ($format) {
            case 'php':
                $translations = include 'data:text/plain;base64,' . base64_encode($data);
                break;
            case 'json':
                $translations = json_decode($data, true);
                break;
            case 'yaml':
                $translations = yaml_parse($data);
                break;
            default:
                $translations = unserialize($data);
        }

        if (is_array($translations)) {
            $this->addTranslations($translations, $locale);
            return true;
        }

        return false;
    }

    /**
     * Get translation statistics
     */
    public function getTranslationStats(): array
    {
        $stats = [];

        foreach ($this->supportedLocales as $locale => $info) {
            $translationCount = isset($this->translations[$locale]) ? count($this->translations[$locale]) : 0;
            $completionRate = $this->calculateCompletionRate($locale);

            $stats[$locale] = [
                'name' => $info['name'],
                'native' => $info['native'],
                'direction' => $info['direction'],
                'flag' => $info['flag'],
                'translations' => $translationCount,
                'completion_rate' => $completionRate
            ];
        }

        return $stats;
    }

    /**
     * Calculate completion rate for a locale
     */
    private function calculateCompletionRate(string $locale): float
    {
        if (!isset($this->translations[$locale]) || empty($this->translations[$this->fallbackLocale])) {
            return 0.0;
        }

        $fallbackTranslations = $this->translations[$this->fallbackLocale];
        $localeTranslations = $this->translations[$locale];

        $translatedKeys = array_intersect_key($localeTranslations, $fallbackTranslations);
        $totalKeys = count($fallbackTranslations);

        return $totalKeys > 0 ? (count($translatedKeys) / $totalKeys) * 100 : 0.0;
    }

    /**
     * Get missing translations
     */
    public function getMissingTranslations(string $locale = null): array
    {
        $locale = $locale ?? $this->currentLocale;

        if (!isset($this->translations[$locale]) || !isset($this->translations[$this->fallbackLocale])) {
            return [];
        }

        $fallbackTranslations = $this->translations[$this->fallbackLocale];
        $localeTranslations = $this->translations[$locale];

        return array_diff_key($fallbackTranslations, $localeTranslations);
    }

    /**
     * Validate translation key
     */
    public function validateTranslationKey(string $key): bool
    {
        // Check for valid key format
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $key) === 1;
    }

    /**
     * Get locale from IP address (geolocation)
     */
    public function getLocaleFromIp(string $ipAddress): ?string
    {
        // This would integrate with a geolocation service
        // For now, return null
        return null;
    }

    /**
     * Generate URL with locale prefix
     */
    public function generateLocalizedUrl(string $path, string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;

        if (!$this->config['url_prefix'] || $locale === $this->fallbackLocale) {
            return $path;
        }

        // Remove leading slash from path if present
        $path = ltrim($path, '/');

        return "/{$locale}/{$path}";
    }

    /**
     * Parse localized URL
     */
    public function parseLocalizedUrl(string $url): array
    {
        $pathParts = explode('/', trim($url, '/'));
        $locale = $pathParts[0] ?? '';

        if (isset($this->supportedLocales[$locale])) {
            $remainingPath = '/' . implode('/', array_slice($pathParts, 1));
            return [
                'locale' => $locale,
                'path' => $remainingPath
            ];
        }

        return [
            'locale' => $this->fallbackLocale,
            'path' => $url
        ];
    }

    /**
     * Get browser language preferences
     */
    public function getBrowserLanguages(): array
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        if (empty($acceptLanguage)) {
            return [];
        }

        $languages = [];
        $languageList = explode(',', $acceptLanguage);

        foreach ($languageList as $language) {
            $parts = explode(';', $language);
            $locale = trim($parts[0]);
            $quality = isset($parts[1]) ? (float) str_replace('q=', '', $parts[1]) : 1.0;

            $languages[] = [
                'locale' => $locale,
                'quality' => $quality,
                'supported' => isset($this->supportedLocales[$locale])
            ];
        }

        // Sort by quality
        usort($languages, function($a, $b) {
            return $b['quality'] <=> $a['quality'];
        });

        return $languages;
    }

    /**
     * Clear translation cache
     */
    public function clearCache(): void
    {
        $this->translations = [];
        $this->loadTranslations();
    }

    /**
     * Get system locale information
     */
    public function getSystemLocaleInfo(): array
    {
        return [
            'current_locale' => $this->currentLocale,
            'fallback_locale' => $this->fallbackLocale,
            'supported_locales_count' => count($this->supportedLocales),
            'rtl_locales' => array_filter($this->supportedLocales, function($locale) {
                return $locale['direction'] === 'rtl';
            }),
            'translation_sources' => $this->config['translation_sources'],
            'cache_enabled' => $this->config['cache_translations'],
            'url_prefix_enabled' => $this->config['url_prefix'],
            'auto_detect_enabled' => $this->config['auto_detect']
        ];
    }
}

// Helper functions for template usage
function __($key, $params = [], $locale = null) {
    global $i18n;
    return $i18n->translate($key, $params, $locale);
}

function _n($key, $count, $params = [], $locale = null) {
    global $i18n;
    return $i18n->translatePlural($key, $count, $params, $locale);
}

function _d($date, $format = 'medium', $locale = null) {
    global $i18n;
    return $i18n->formatDate($date, $format, $locale);
}

function _n($number, $decimals = null, $locale = null) {
    global $i18n;
    return $i18n->formatNumber($number, $decimals, $locale);
}

function _c($amount, $currency = null, $locale = null) {
    global $i18n;
    return $i18n->formatCurrency($amount, $currency, $locale);
}
