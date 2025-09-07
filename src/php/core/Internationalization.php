<?php
/**
 * TPT Government Platform - Internationalization (i18n) System
 *
 * Comprehensive internationalization and localization system
 * supporting 50+ languages for government applications
 */

namespace Core;

class Internationalization
{
    /**
     * Current language code
     */
    private string $currentLanguage = 'en';

    /**
     * Fallback language code
     */
    private string $fallbackLanguage = 'en';

    /**
     * Available languages
     */
    private array $availableLanguages = [
        'en' => ['name' => 'English', 'native' => 'English', 'rtl' => false],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'rtl' => false],
        'fr' => ['name' => 'French', 'native' => 'Français', 'rtl' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'rtl' => false],
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'rtl' => false],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'rtl' => false],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'rtl' => false],
        'ru' => ['name' => 'Russian', 'native' => 'Русский', 'rtl' => false],
        'ja' => ['name' => 'Japanese', 'native' => '日本語', 'rtl' => false],
        'ko' => ['name' => 'Korean', 'native' => '한국어', 'rtl' => false],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'rtl' => false],
        'nl' => ['name' => 'Dutch', 'native' => 'Nederlands', 'rtl' => false],
        'sv' => ['name' => 'Swedish', 'native' => 'Svenska', 'rtl' => false],
        'da' => ['name' => 'Danish', 'native' => 'Dansk', 'rtl' => false],
        'no' => ['name' => 'Norwegian', 'native' => 'Norsk', 'rtl' => false],
        'fi' => ['name' => 'Finnish', 'native' => 'Suomi', 'rtl' => false],
        'pl' => ['name' => 'Polish', 'native' => 'Polski', 'rtl' => false],
        'tr' => ['name' => 'Turkish', 'native' => 'Türkçe', 'rtl' => false],
        'he' => ['name' => 'Hebrew', 'native' => 'עברית', 'rtl' => true],
        'fa' => ['name' => 'Persian', 'native' => 'فارسی', 'rtl' => true],
        'ur' => ['name' => 'Urdu', 'native' => 'اردو', 'rtl' => true],
        'th' => ['name' => 'Thai', 'native' => 'ไทย', 'rtl' => false],
        'vi' => ['name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'rtl' => false],
        'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'rtl' => false],
        'ms' => ['name' => 'Malay', 'native' => 'Bahasa Melayu', 'rtl' => false],
        'tl' => ['name' => 'Filipino', 'native' => 'Filipino', 'rtl' => false],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili', 'rtl' => false],
        'am' => ['name' => 'Amharic', 'native' => 'አማርኛ', 'rtl' => false],
        'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'rtl' => false],
        'ig' => ['name' => 'Igbo', 'native' => 'Igbo', 'rtl' => false],
        'ha' => ['name' => 'Hausa', 'native' => 'Hausa', 'rtl' => true],
        'bn' => ['name' => 'Bengali', 'native' => 'বাংলা', 'rtl' => false],
        'ta' => ['name' => 'Tamil', 'native' => 'தமிழ்', 'rtl' => false],
        'te' => ['name' => 'Telugu', 'native' => 'తెలుగు', 'rtl' => false],
        'mr' => ['name' => 'Marathi', 'native' => 'मराठी', 'rtl' => false],
        'gu' => ['name' => 'Gujarati', 'native' => 'ગુજરાતી', 'rtl' => false],
        'kn' => ['name' => 'Kannada', 'native' => 'ಕನ್ನಡ', 'rtl' => false],
        'ml' => ['name' => 'Malayalam', 'native' => 'മലയാളം', 'rtl' => false],
        'pa' => ['name' => 'Punjabi', 'native' => 'ਪੰਜਾਬੀ', 'rtl' => false],
        'uk' => ['name' => 'Ukrainian', 'native' => 'Українська', 'rtl' => false],
        'cs' => ['name' => 'Czech', 'native' => 'Čeština', 'rtl' => false],
        'sk' => ['name' => 'Slovak', 'native' => 'Slovenčina', 'rtl' => false],
        'hr' => ['name' => 'Croatian', 'native' => 'Hrvatski', 'rtl' => false],
        'sl' => ['name' => 'Slovenian', 'native' => 'Slovenščina', 'rtl' => false],
        'et' => ['name' => 'Estonian', 'native' => 'Eesti', 'rtl' => false],
        'lv' => ['name' => 'Latvian', 'native' => 'Latviešu', 'rtl' => false],
        'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių', 'rtl' => false],
        'bg' => ['name' => 'Bulgarian', 'native' => 'Български', 'rtl' => false],
        'ro' => ['name' => 'Romanian', 'native' => 'Română', 'rtl' => false],
        'hu' => ['name' => 'Hungarian', 'native' => 'Magyar', 'rtl' => false],
        'el' => ['name' => 'Greek', 'native' => 'Ελληνικά', 'rtl' => false],
        'ka' => ['name' => 'Georgian', 'native' => 'ქართული', 'rtl' => false],
        'az' => ['name' => 'Azerbaijani', 'native' => 'Azərbaycan', 'rtl' => false],
        'kk' => ['name' => 'Kazakh', 'native' => 'Қазақша', 'rtl' => false],
        'uz' => ['name' => 'Uzbek', 'native' => 'Oʻzbekcha', 'rtl' => false]
    ];

    /**
     * Translation cache
     */
    private array $translationCache = [];

    /**
     * Loaded translation files
     */
    private array $loadedTranslations = [];

    /**
     * Number formatting rules
     */
    private array $numberFormats = [];

    /**
     * Date/time formatting rules
     */
    private array $dateFormats = [];

    /**
     * Currency formatting rules
     */
    private array $currencyFormats = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initializeLocale();
        $this->loadConfiguration();
        $this->loadTranslations();
    }

    /**
     * Initialize locale settings
     */
    private function initializeLocale(): void
    {
        // Detect user's preferred language
        $this->detectUserLanguage();

        // Set PHP locale
        $this->setLocale();

        // Configure number and date formatting
        $this->configureFormatting();
    }

    /**
     * Detect user's preferred language
     */
    private function detectUserLanguage(): void
    {
        // Check URL parameter
        if (isset($_GET['lang']) && $this->isLanguageAvailable($_GET['lang'])) {
            $this->currentLanguage = $_GET['lang'];
            $this->setLanguageCookie($this->currentLanguage);
            return;
        }

        // Check session
        if (isset($_SESSION['language']) && $this->isLanguageAvailable($_SESSION['language'])) {
            $this->currentLanguage = $_SESSION['language'];
            return;
        }

        // Check cookie
        if (isset($_COOKIE['tpt_language']) && $this->isLanguageAvailable($_COOKIE['tpt_language'])) {
            $this->currentLanguage = $_COOKIE['tpt_language'];
            return;
        }

        // Check browser Accept-Language header
        $this->detectBrowserLanguage();

        // Store in session
        $_SESSION['language'] = $this->currentLanguage;
    }

    /**
     * Detect browser's preferred language
     */
    private function detectBrowserLanguage(): void
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return;
        }

        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            $langCode = trim(explode(';', $language)[0]);
            $primaryCode = explode('-', $langCode)[0];

            if ($this->isLanguageAvailable($langCode)) {
                $this->currentLanguage = $langCode;
                return;
            }

            if ($this->isLanguageAvailable($primaryCode)) {
                $this->currentLanguage = $primaryCode;
                return;
            }
        }
    }

    /**
     * Set PHP locale
     */
    private function setLocale(): void
    {
        $localeMap = [
            'en' => 'en_US.UTF-8',
            'es' => 'es_ES.UTF-8',
            'fr' => 'fr_FR.UTF-8',
            'de' => 'de_DE.UTF-8',
            'zh' => 'zh_CN.UTF-8',
            'ar' => 'ar_SA.UTF-8',
            'hi' => 'hi_IN.UTF-8',
            'pt' => 'pt_BR.UTF-8',
            'ru' => 'ru_RU.UTF-8',
            'ja' => 'ja_JP.UTF-8',
            'ko' => 'ko_KR.UTF-8'
        ];

        $locale = $localeMap[$this->currentLanguage] ?? $localeMap[$this->fallbackLanguage];

        if (function_exists('setlocale')) {
            setlocale(LC_ALL, $locale);
        }

        if (function_exists('putenv')) {
            putenv("LC_ALL=$locale");
        }
    }

    /**
     * Configure number, date, and currency formatting
     */
    private function configureFormatting(): void
    {
        // Number formatting rules
        $this->numberFormats = [
            'en' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'de' => ['decimal' => ',', 'thousands' => '.', 'precision' => 2],
            'fr' => ['decimal' => ',', 'thousands' => ' ', 'precision' => 2],
            'es' => ['decimal' => ',', 'thousands' => '.', 'precision' => 2],
            'ar' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'zh' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2],
            'ja' => ['decimal' => '.', 'thousands' => ',', 'precision' => 0],
            'hi' => ['decimal' => '.', 'thousands' => ',', 'precision' => 2]
        ];

        // Date formatting rules
        $this->dateFormats = [
            'en' => ['date' => 'm/d/Y', 'time' => 'g:i A', 'datetime' => 'm/d/Y g:i A'],
            'de' => ['date' => 'd.m.Y', 'time' => 'H:i', 'datetime' => 'd.m.Y H:i'],
            'fr' => ['date' => 'd/m/Y', 'time' => 'H:i', 'datetime' => 'd/m/Y H:i'],
            'es' => ['date' => 'd/m/Y', 'time' => 'H:i', 'datetime' => 'd/m/Y H:i'],
            'ar' => ['date' => 'd/m/Y', 'time' => 'H:i', 'datetime' => 'd/m/Y H:i'],
            'zh' => ['date' => 'Y-m-d', 'time' => 'H:i', 'datetime' => 'Y-m-d H:i'],
            'ja' => ['date' => 'Y/m/d', 'time' => 'H:i', 'datetime' => 'Y/m/d H:i'],
            'hi' => ['date' => 'd/m/Y', 'time' => 'H:i', 'datetime' => 'd/m/Y H:i']
        ];

        // Currency formatting rules
        $this->currencyFormats = [
            'en' => ['symbol' => '$', 'position' => 'before', 'code' => 'USD'],
            'de' => ['symbol' => '€', 'position' => 'after', 'code' => 'EUR'],
            'fr' => ['symbol' => '€', 'position' => 'after', 'code' => 'EUR'],
            'es' => ['symbol' => '€', 'position' => 'before', 'code' => 'EUR'],
            'ar' => ['symbol' => 'ر.س', 'position' => 'before', 'code' => 'SAR'],
            'zh' => ['symbol' => '¥', 'position' => 'before', 'code' => 'CNY'],
            'ja' => ['symbol' => '¥', 'position' => 'before', 'code' => 'JPY'],
            'hi' => ['symbol' => '₹', 'position' => 'before', 'code' => 'INR']
        ];
    }

    /**
     * Load configuration
     */
    private function loadConfiguration(): void
    {
        $configFile = CONFIG_PATH . '/i18n.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->fallbackLanguage = $config['fallback_language'] ?? 'en';

            if (isset($config['available_languages'])) {
                $this->availableLanguages = array_merge($this->availableLanguages, $config['available_languages']);
            }
        }
    }

    /**
     * Load translation files
     */
    private function loadTranslations(): void
    {
        $langDir = RESOURCES_PATH . '/lang';

        // Load current language translations
        $this->loadLanguageFile($langDir, $this->currentLanguage);

        // Load fallback language if different
        if ($this->currentLanguage !== $this->fallbackLanguage) {
            $this->loadLanguageFile($langDir, $this->fallbackLanguage);
        }
    }

    /**
     * Load language file
     */
    private function loadLanguageFile(string $langDir, string $language): void
    {
        $langFile = $langDir . '/' . $language . '.php';

        if (file_exists($langFile)) {
            $translations = require $langFile;
            $this->loadedTranslations[$language] = $translations;
        }
    }

    /**
     * Translate a key
     */
    public function translate(string $key, array $parameters = [], string $domain = 'messages'): string
    {
        // Check cache first
        $cacheKey = $this->currentLanguage . '.' . $domain . '.' . $key;
        if (isset($this->translationCache[$cacheKey])) {
            return $this->replaceParameters($this->translationCache[$cacheKey], $parameters);
        }

        // Find translation
        $translation = $this->findTranslation($key, $domain);

        // Cache the result
        $this->translationCache[$cacheKey] = $translation;

        return $this->replaceParameters($translation, $parameters);
    }

    /**
     * Find translation for a key
     */
    private function findTranslation(string $key, string $domain): string
    {
        // Check current language
        if (isset($this->loadedTranslations[$this->currentLanguage][$domain][$key])) {
            return $this->loadedTranslations[$this->currentLanguage][$domain][$key];
        }

        // Check fallback language
        if ($this->currentLanguage !== $this->fallbackLanguage &&
            isset($this->loadedTranslations[$this->fallbackLanguage][$domain][$key])) {
            return $this->loadedTranslations[$this->fallbackLanguage][$domain][$key];
        }

        // Return key if no translation found
        return $key;
    }

    /**
     * Replace parameters in translation
     */
    private function replaceParameters(string $translation, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $translation = str_replace(':' . $key, $value, $translation);
            $translation = str_replace('{' . $key . '}', $value, $translation);
        }

        return $translation;
    }

    /**
     * Format number according to locale
     */
    public function formatNumber(float $number, int $decimals = null): string
    {
        $format = $this->numberFormats[$this->currentLanguage] ?? $this->numberFormats['en'];

        if ($decimals === null) {
            $decimals = $format['precision'];
        }

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
    public function formatCurrency(float $amount, string $currency = null): string
    {
        $format = $this->currencyFormats[$this->currentLanguage] ?? $this->currencyFormats['en'];

        if ($currency === null) {
            $currency = $format['code'];
        }

        $formattedAmount = $this->formatNumber($amount, 2);
        $symbol = $format['symbol'];

        if ($format['position'] === 'before') {
            return $symbol . $formattedAmount;
        } else {
            return $formattedAmount . ' ' . $symbol;
        }
    }

    /**
     * Format date according to locale
     */
    public function formatDate(\DateTime $date, string $format = 'date'): string
    {
        $dateFormats = $this->dateFormats[$this->currentLanguage] ?? $this->dateFormats['en'];
        $formatString = $dateFormats[$format] ?? $dateFormats['date'];

        return $date->format($formatString);
    }

    /**
     * Get localized month names
     */
    public function getMonthNames(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $date = \DateTime::createFromFormat('m', $i);
            $months[] = $date->format('F'); // Full month name
        }

        return $months;
    }

    /**
     * Get localized day names
     */
    public function getDayNames(): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = new \DateTime('next Monday +' . $i . ' days');
            $days[] = $date->format('l'); // Full day name
        }

        return $days;
    }

    /**
     * Set current language
     */
    public function setLanguage(string $language): bool
    {
        if (!$this->isLanguageAvailable($language)) {
            return false;
        }

        $this->currentLanguage = $language;
        $_SESSION['language'] = $language;
        $this->setLanguageCookie($language);

        // Reload translations
        $this->loadTranslations();

        // Clear translation cache
        $this->translationCache = [];

        return true;
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * Check if language is available
     */
    public function isLanguageAvailable(string $language): bool
    {
        return isset($this->availableLanguages[$language]);
    }

    /**
     * Check if current language is RTL
     */
    public function isRTL(): bool
    {
        return $this->availableLanguages[$this->currentLanguage]['rtl'] ?? false;
    }

    /**
     * Get language info
     */
    public function getLanguageInfo(string $language = null): ?array
    {
        $lang = $language ?? $this->currentLanguage;
        return $this->availableLanguages[$lang] ?? null;
    }

    /**
     * Set language cookie
     */
    private function setLanguageCookie(string $language): void
    {
        setcookie('tpt_language', $language, time() + (365 * 24 * 60 * 60), '/'); // 1 year
    }

    /**
     * Add translation
     */
    public function addTranslation(string $key, string $translation, string $domain = 'messages'): void
    {
        if (!isset($this->loadedTranslations[$this->currentLanguage][$domain])) {
            $this->loadedTranslations[$this->currentLanguage][$domain] = [];
        }

        $this->loadedTranslations[$this->currentLanguage][$domain][$key] = $translation;

        // Clear cache for this key
        $cacheKey = $this->currentLanguage . '.' . $domain . '.' . $key;
        unset($this->translationCache[$cacheKey]);
    }

    /**
     * Load translations from database
     */
    public function loadDatabaseTranslations(): void
    {
        // Implementation for loading translations from database
        // This would integrate with a translations table
    }

    /**
     * Export translations to file
     */
    public function exportTranslations(string $language, string $domain = 'messages'): bool
    {
        if (!isset($this->loadedTranslations[$language][$domain])) {
            return false;
        }

        $translations = $this->loadedTranslations[$language][$domain];
        $exportPath = RESOURCES_PATH . '/lang/' . $language . '_' . $domain . '_export.php';

        $content = "<?php\n\nreturn " . var_export($translations, true) . ";\n";

        return file_put_contents($exportPath, $content) !== false;
    }

    /**
     * Import translations from file
     */
    public function importTranslations(string $filePath, string $language, string $domain = 'messages'): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $translations = require $filePath;

        if (!is_array($translations)) {
            return false;
        }

        if (!isset($this->loadedTranslations[$language])) {
            $this->loadedTranslations[$language] = [];
        }

        if (!isset($this->loadedTranslations[$language][$domain])) {
            $this->loadedTranslations[$language][$domain] = [];
        }

        $this->loadedTranslations[$language][$domain] = array_merge(
            $this->loadedTranslations[$language][$domain],
            $translations
        );

        return true;
    }

    /**
     * Get translation statistics
     */
    public function getTranslationStats(): array
    {
        $stats = [
            'current_language' => $this->currentLanguage,
            'fallback_language' => $this->fallbackLanguage,
            'available_languages' => count($this->availableLanguages),
            'loaded_languages' => count($this->loadedTranslations),
            'cache_size' => count($this->translationCache)
        ];

        // Count translations per domain
        $stats['translations_per_domain'] = [];
        foreach ($this->loadedTranslations as $lang => $domains) {
            foreach ($domains as $domain => $translations) {
                if (!isset($stats['translations_per_domain'][$domain])) {
                    $stats['translations_per_domain'][$domain] = [];
                }
                $stats['translations_per_domain'][$domain][$lang] = count($translations);
            }
        }

        return $stats;
    }

    /**
     * Clear translation cache
     */
    public function clearCache(): void
    {
        $this->translationCache = [];
    }

    /**
     * Get RTL languages
     */
    public function getRTLLanguages(): array
    {
        return array_filter($this->availableLanguages, function($lang) {
            return $lang['rtl'] === true;
        });
    }

    /**
     * Validate translation key
     */
    public function validateTranslationKey(string $key): bool
    {
        // Basic validation - can be extended
        return !empty($key) && !preg_match('/[^a-zA-Z0-9_\.]/', $key);
    }

    /**
     * Get missing translations
     */
    public function getMissingTranslations(string $language, string $domain = 'messages'): array
    {
        if (!isset($this->loadedTranslations[$this->fallbackLanguage][$domain])) {
            return [];
        }

        $fallbackTranslations = $this->loadedTranslations[$this->fallbackLanguage][$domain];
        $currentTranslations = $this->loadedTranslations[$language][$domain] ?? [];

        return array_diff_key($fallbackTranslations, $currentTranslations);
    }

    /**
     * Pluralize text
     */
    public function pluralize(string $singular, string $plural, int $count): string
    {
        $key = $count === 1 ? $singular : $plural;
        return $this->translate($key, ['count' => $count]);
    }

    /**
     * Get locale-specific date/time formats
     */
    public function getDateTimeFormats(): array
    {
        return $this->dateFormats[$this->currentLanguage] ?? $this->dateFormats['en'];
    }

    /**
     * Format relative time
     */
    public function formatRelativeTime(\DateTime $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->days === 0) {
            if ($diff->h === 0) {
                if ($diff->i === 0) {
                    return $this->translate('just_now');
                }
                return $this->pluralize('minute_ago', 'minutes_ago', $diff->i);
            }
            return $this->pluralize('hour_ago', 'hours_ago', $diff->h);
        }

        if ($diff->days < 7) {
            return $this->pluralize('day_ago', 'days_ago', $diff->days);
        }

        if ($diff->days < 30) {
            $weeks = floor($diff->days / 7);
            return $this->pluralize('week_ago', 'weeks_ago', $weeks);
        }

        if ($diff->days < 365) {
            $months = floor($diff->days / 30);
            return $this->pluralize('month_ago', 'months_ago', $months);
        }

        $years = floor($diff->days / 365);
        return $this->pluralize('year_ago', 'years_ago', $years);
    }
}

// Helper functions for global use
if (!function_exists('__')) {
    function __(string $key, array $parameters = [], string $domain = 'messages'): string {
        $i18n = new \Core\Internationalization();
        return $i18n->translate($key, $parameters, $domain);
    }
}

if (!function_exists('_n')) {
    function _n(string $singular, string $plural, int $count): string {
        $i18n = new \Core\Internationalization();
        return $i18n->pluralize($singular, $plural, $count);
    }
}

if (!function_exists('_x')) {
    function _x(string $key, string $context, array $parameters = []): string {
        $i18n = new \Core\Internationalization();
        return $i18n->translate($key, $parameters, $context);
    }
}
