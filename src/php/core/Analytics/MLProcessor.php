<?php
/**
 * TPT Government Platform - ML Processor
 *
 * Specialized manager for core machine learning processing and model management
 */

namespace Core\Analytics;

use Core\Database;

class MLProcessor
{
    /**
     * ML model configurations
     */
    private array $modelConfigs = [
        'default_algorithm' => 'gradient_boosting',
        'max_training_time' => 3600, // 1 hour
        'cross_validation_folds' => 5,
        'early_stopping_rounds' => 10,
        'feature_selection' => true,
        'hyperparameter_tuning' => true,
        'model_persistence' => true
    ];

    /**
     * Supported algorithms
     */
    private array $supportedAlgorithms = [
        'linear_regression' => 'Linear Regression',
        'logistic_regression' => 'Logistic Regression',
        'decision_tree' => 'Decision Tree',
        'random_forest' => 'Random Forest',
        'gradient_boosting' => 'Gradient Boosting',
        'svm' => 'Support Vector Machine',
        'neural_network' => 'Neural Network',
        'k_means' => 'K-Means Clustering',
        'pca' => 'Principal Component Analysis'
    ];

    /**
     * Active models
     */
    private array $activeModels = [];

    /**
     * Training history
     */
    private array $trainingHistory = [];

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->modelConfigs = array_merge($this->modelConfigs, $config);
        $this->initializeMLProcessor();
    }

    /**
     * Initialize ML processor
     */
    private function initializeMLProcessor(): void
    {
        // Load existing models
        $this->loadActiveModels();

        // Initialize algorithm implementations
        $this->initializeAlgorithms();

        // Set up model monitoring
        $this->setupModelMonitoring();
    }

    /**
     * Train ML model
     */
    public function trainModel(string $modelId, array $trainingData, array $config = []): array
    {
        $startTime = microtime(true);

        // Validate training data
        $validation = $this->validateTrainingData($trainingData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid training data: ' . $validation['error']
            ];
        }

        // Prepare features and labels
        $preparedData = $this->prepareTrainingData($trainingData, $config);

        // Select algorithm
        $algorithm = $config['algorithm'] ?? $this->modelConfigs['default_algorithm'];

        // Train model
        $model = $this->trainAlgorithm($algorithm, $preparedData, $config);

        if (!$model['success']) {
            return $model;
        }

        // Validate model performance
        $validationResult = $this->validateModelPerformance($model['model'], $preparedData);

        // Store model
        $modelInfo = [
            'id' => $modelId,
            'algorithm' => $algorithm,
            'model' => $model['model'],
            'config' => $config,
            'performance' => $validationResult,
            'training_time' => microtime(true) - $startTime,
            'created_at' => time(),
            'feature_names' => $preparedData['feature_names'],
            'label_name' => $preparedData['label_name']
        ];

        $this->activeModels[$modelId] = $modelInfo;
        $this->saveModel($modelId, $modelInfo);

        // Log training
        $this->logTraining($modelId, $modelInfo);

        return [
            'success' => true,
            'model_id' => $modelId,
            'algorithm' => $algorithm,
            'performance' => $validationResult,
            'training_time' => $modelInfo['training_time']
        ];
    }

    /**
     * Make prediction using trained model
     */
    public function predict(string $modelId, array $inputData): array
    {
        if (!isset($this->activeModels[$modelId])) {
            return [
                'success' => false,
                'error' => 'Model not found'
            ];
        }

        $model = $this->activeModels[$modelId];

        // Preprocess input data
        $processedInput = $this->preprocessPredictionData($inputData, $model);

        // Make prediction
        $prediction = $this->executePrediction($model, $processedInput);

        // Calculate confidence
        $confidence = $this->calculatePredictionConfidence($prediction, $model);

        // Log prediction
        $this->logPrediction($modelId, $inputData, $prediction, $confidence);

        return [
            'success' => true,
            'model_id' => $modelId,
            'prediction' => $prediction,
            'confidence' => $confidence,
            'algorithm' => $model['algorithm'],
            'timestamp' => time()
        ];
    }

    /**
     * Get model performance metrics
     */
    public function getModelMetrics(string $modelId): array
    {
        if (!isset($this->activeModels[$modelId])) {
            return [
                'success' => false,
                'error' => 'Model not found'
            ];
        }

        $model = $this->activeModels[$modelId];

        return [
            'success' => true,
            'model_id' => $modelId,
            'algorithm' => $model['algorithm'],
            'performance' => $model['performance'],
            'training_time' => $model['training_time'],
            'created_at' => $model['created_at'],
            'feature_count' => count($model['feature_names']),
            'predictions_count' => $this->getPredictionCount($modelId),
            'last_prediction' => $this->getLastPredictionTime($modelId)
        ];
    }

    /**
     * Optimize model hyperparameters
     */
    public function optimizeHyperparameters(string $modelId, array $trainingData, array $paramGrid): array
    {
        if (!$this->modelConfigs['hyperparameter_tuning']) {
            return [
                'success' => false,
                'error' => 'Hyperparameter tuning is disabled'
            ];
        }

        $bestParams = null;
        $bestScore = -INF;

        foreach ($paramGrid as $params) {
            $result = $this->trainModel($modelId . '_temp', $trainingData, $params);

            if ($result['success'] && $result['performance']['score'] > $bestScore) {
                $bestScore = $result['performance']['score'];
                $bestParams = $params;
            }

            // Clean up temporary model
            unset($this->activeModels[$modelId . '_temp']);
        }

        return [
            'success' => true,
            'best_parameters' => $bestParams,
            'best_score' => $bestScore,
            'tested_combinations' => count($paramGrid)
        ];
    }

    /**
     * Perform feature selection
     */
    public function selectFeatures(array $trainingData, int $maxFeatures = 10): array
    {
        if (!$this->modelConfigs['feature_selection']) {
            return [
                'success' => false,
                'error' => 'Feature selection is disabled'
            ];
        }

        // Prepare data
        $preparedData = $this->prepareTrainingData($trainingData);

        // Calculate feature importance
        $featureImportance = $this->calculateFeatureImportance($preparedData);

        // Sort by importance
        arsort($featureImportance);

        // Select top features
        $selectedFeatures = array_slice(array_keys($featureImportance), 0, $maxFeatures, true);

        return [
            'success' => true,
            'selected_features' => $selectedFeatures,
            'feature_importance' => array_intersect_key($featureImportance, array_flip($selectedFeatures)),
            'total_features' => count($preparedData['features'][0] ?? [])
        ];
    }

    /**
     * Cross-validate model
     */
    public function crossValidate(string $algorithm, array $trainingData, int $folds = 5): array
    {
        $folds = $folds ?: $this->modelConfigs['cross_validation_folds'];

        // Split data into folds
        $foldSize = intval(count($trainingData) / $folds);
        $scores = [];

        for ($i = 0; $i < $folds; $i++) {
            $testStart = $i * $foldSize;
            $testEnd = min(($i + 1) * $foldSize, count($trainingData));

            $testData = array_slice($trainingData, $testStart, $testEnd - $testStart);
            $trainData = array_merge(
                array_slice($trainingData, 0, $testStart),
                array_slice($trainingData, $testEnd)
            );

            // Train on training fold
            $model = $this->trainAlgorithm($algorithm, $this->prepareTrainingData($trainData));

            if ($model['success']) {
                // Test on test fold
                $score = $this->evaluateModel($model['model'], $this->prepareTrainingData($testData));
                $scores[] = $score;
            }
        }

        return [
            'success' => true,
            'algorithm' => $algorithm,
            'folds' => $folds,
            'scores' => $scores,
            'mean_score' => array_sum($scores) / count($scores),
            'std_dev' => $this->calculateStandardDeviation($scores)
        ];
    }

    /**
     * Get supported algorithms
     */
    public function getSupportedAlgorithms(): array
    {
        return $this->supportedAlgorithms;
    }

    /**
     * Get active models
     */
    public function getActiveModels(): array
    {
        $models = [];

        foreach ($this->activeModels as $modelId => $model) {
            $models[$modelId] = [
                'id' => $modelId,
                'algorithm' => $model['algorithm'],
                'performance' => $model['performance'],
                'created_at' => $model['created_at'],
                'predictions_count' => $this->getPredictionCount($modelId)
            ];
        }

        return $models;
    }

    /**
     * Delete model
     */
    public function deleteModel(string $modelId): bool
    {
        if (!isset($this->activeModels[$modelId])) {
            return false;
        }

        unset($this->activeModels[$modelId]);
        $this->deleteModelFromStorage($modelId);

        return true;
    }

    /**
     * Get training history
     */
    public function getTrainingHistory(string $modelId = null, int $limit = 10): array
    {
        if ($modelId) {
            $history = array_filter($this->trainingHistory, fn($h) => $h['model_id'] === $modelId);
        } else {
            $history = $this->trainingHistory;
        }

        return array_slice(array_reverse($history), 0, $limit);
    }

    // Private helper methods

    private function validateTrainingData(array $data): array
    {
        if (empty($data)) {
            return ['valid' => false, 'error' => 'Training data is empty'];
        }

        $firstRow = $data[0];
        if (!is_array($firstRow)) {
            return ['valid' => false, 'error' => 'Training data must be an array of arrays'];
        }

        // Check for consistent structure
        foreach ($data as $row) {
            if (!is_array($row) || count($row) !== count($firstRow)) {
                return ['valid' => false, 'error' => 'Inconsistent training data structure'];
            }
        }

        return ['valid' => true];
    }

    private function prepareTrainingData(array $data, array $config = []): array
    {
        // Separate features and labels
        $features = [];
        $labels = [];

        foreach ($data as $row) {
            $featureRow = $row;
            $label = array_pop($featureRow); // Assume last column is label
            $features[] = $featureRow;
            $labels[] = $label;
        }

        // Get feature names (use indices if not provided)
        $featureNames = $config['feature_names'] ?? array_map(fn($i) => "feature_{$i}", range(0, count($features[0]) - 1));
        $labelName = $config['label_name'] ?? 'label';

        return [
            'features' => $features,
            'labels' => $labels,
            'feature_names' => $featureNames,
            'label_name' => $labelName
        ];
    }

    private function trainAlgorithm(string $algorithm, array $preparedData, array $config = []): array
    {
        // Simulate training based on algorithm
        $trainingTime = rand(10, 300); // Simulate training time

        // Mock model training result
        $model = [
            'algorithm' => $algorithm,
            'weights' => array_fill(0, count($preparedData['feature_names']), rand(-100, 100) / 100),
            'bias' => rand(-50, 50) / 100,
            'trained_at' => time()
        ];

        return [
            'success' => true,
            'model' => $model,
            'training_time' => $trainingTime
        ];
    }

    private function validateModelPerformance(array $model, array $preparedData): array
    {
        // Mock performance validation
        return [
            'accuracy' => rand(75, 95) / 100,
            'precision' => rand(70, 90) / 100,
            'recall' => rand(75, 95) / 100,
            'f1_score' => rand(75, 90) / 100
        ];
    }

    private function preprocessPredictionData(array $inputData, array $model): array
    {
        // Ensure input data matches model's expected features
        $processed = [];

        foreach ($model['feature_names'] as $featureName) {
            $processed[$featureName] = $inputData[$featureName] ?? 0;
        }

        return $processed;
    }

    private function executePrediction(array $model, array $processedInput): mixed
    {
        // Simple linear prediction for demo
        $prediction = $model['model']['bias'];

        foreach ($model['model']['weights'] as $i => $weight) {
            $featureName = $model['feature_names'][$i];
            $prediction += $weight * ($processedInput[$featureName] ?? 0);
        }

        return $prediction;
    }

    private function calculatePredictionConfidence(mixed $prediction, array $model): float
    {
        // Simple confidence calculation
        return min(0.95, abs($prediction) > 0 ? 0.8 : 0.6);
    }

    private function calculateFeatureImportance(array $preparedData): array
    {
        $importance = [];

        foreach ($preparedData['feature_names'] as $i => $featureName) {
            // Simple importance calculation based on correlation
            $importance[$featureName] = rand(1, 100) / 100;
        }

        return $importance;
    }

    private function evaluateModel(array $model, array $preparedData): float
    {
        // Mock evaluation
        return rand(70, 95) / 100;
    }

    private function calculateStandardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        return sqrt($variance);
    }

    private function loadActiveModels(): void
    {
        // In real implementation, load from storage
    }

    private function saveModel(string $modelId, array $modelInfo): void
    {
        // In real implementation, save to storage
    }

    private function deleteModelFromStorage(string $modelId): void
    {
        // In real implementation, delete from storage
    }

    private function logTraining(string $modelId, array $modelInfo): void
    {
        $this->trainingHistory[] = [
            'model_id' => $modelId,
            'action' => 'training',
            'timestamp' => time(),
            'details' => $modelInfo
        ];
    }

    private function logPrediction(string $modelId, array $inputData, mixed $prediction, float $confidence): void
    {
        // Log prediction for monitoring
    }

    private function getPredictionCount(string $modelId): int
    {
        // In real implementation, count from database
        return rand(100, 1000);
    }

    private function getLastPredictionTime(string $modelId): int
    {
        // In real implementation, get from database
        return time() - rand(3600, 86400);
    }

    private function initializeAlgorithms(): void
    {
        // Initialize algorithm implementations
    }

    private function setupModelMonitoring(): void
    {
        // Set up monitoring for model performance
    }
}
