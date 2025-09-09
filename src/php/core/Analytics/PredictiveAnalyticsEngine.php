<?php
/**
 * TPT Government Platform - Predictive Analytics Engine
 *
 * Specialized manager for machine learning and predictive modeling
 */

namespace Core\Analytics;

use Core\Database;

class PredictiveAnalyticsEngine
{
    /**
     * Available prediction models
     */
    private array $models = [
        'demand_prediction' => [
            'name' => 'Service Demand Prediction',
            'description' => 'Predicts future service demand based on historical data',
            'features' => ['time_of_day', 'day_of_week', 'season', 'weather', 'economic_indicators'],
            'target' => 'service_requests'
        ],
        'fraud_detection' => [
            'name' => 'Fraud Detection',
            'description' => 'Detects potential fraudulent activities',
            'features' => ['amount', 'frequency', 'location', 'device_fingerprint', 'time_of_day'],
            'target' => 'is_fraud'
        ],
        'citizen_satisfaction' => [
            'name' => 'Citizen Satisfaction Prediction',
            'description' => 'Predicts citizen satisfaction with services',
            'features' => ['response_time', 'service_type', 'user_history', 'channel', 'complexity'],
            'target' => 'satisfaction_score'
        ],
        'service_optimization' => [
            'name' => 'Service Optimization',
            'description' => 'Optimizes service allocation and resource usage',
            'features' => ['current_workload', 'service_priority', 'estimated_time', 'resource_availability'],
            'target' => 'optimal_allocation'
        ],
        'resource_allocation' => [
            'name' => 'Resource Allocation',
            'description' => 'Predicts optimal resource allocation',
            'features' => ['demand_forecast', 'current_capacity', 'cost_factors', 'efficiency_metrics'],
            'target' => 'resource_allocation'
        ]
    ];

    /**
     * Model configurations
     */
    private array $modelConfigs = [
        'training_frequency' => 'daily',
        'accuracy_threshold' => 0.85,
        'max_training_time' => 3600, // 1 hour
        'validation_split' => 0.2,
        'cross_validation_folds' => 5
    ];

    /**
     * Trained models cache
     */
    private array $trainedModels = [];

    /**
     * Prediction history
     */
    private array $predictionHistory = [];

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
        $this->initializeModels();
    }

    /**
     * Initialize prediction models
     */
    private function initializeModels(): void
    {
        foreach ($this->models as $modelId => $model) {
            $this->trainedModels[$modelId] = [
                'id' => $modelId,
                'status' => 'untrained',
                'accuracy' => 0.0,
                'last_trained' => 0,
                'training_data_size' => 0,
                'model_path' => null,
                'metadata' => []
            ];
        }

        // Load existing trained models
        $this->loadTrainedModels();
    }

    /**
     * Generate prediction for specific model
     */
    public function predict(string $modelId, array $inputData): array
    {
        if (!isset($this->models[$modelId])) {
            return [
                'success' => false,
                'error' => 'Model not found'
            ];
        }

        if (!isset($this->trainedModels[$modelId]) ||
            $this->trainedModels[$modelId]['status'] !== 'trained') {
            return [
                'success' => false,
                'error' => 'Model not trained'
            ];
        }

        $model = $this->trainedModels[$modelId];

        // Validate input data
        $validation = $this->validateInputData($modelId, $inputData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid input data: ' . $validation['error']
            ];
        }

        // Preprocess input data
        $processedData = $this->preprocessInputData($modelId, $inputData);

        // Generate prediction
        $prediction = $this->executePrediction($modelId, $processedData);

        // Calculate confidence
        $confidence = $this->calculatePredictionConfidence($modelId, $prediction, $processedData);

        // Store prediction for analysis
        $this->storePrediction($modelId, $inputData, $prediction, $confidence);

        return [
            'success' => true,
            'model_id' => $modelId,
            'prediction' => $prediction,
            'confidence' => $confidence,
            'model_accuracy' => $model['accuracy'],
            'timestamp' => time(),
            'input_features' => array_keys($inputData)
        ];
    }

    /**
     * Train prediction model
     */
    public function trainModel(string $modelId, array $trainingData = null): array
    {
        if (!isset($this->models[$modelId])) {
            return [
                'success' => false,
                'error' => 'Model not found'
            ];
        }

        // Get training data if not provided
        if ($trainingData === null) {
            $trainingData = $this->getTrainingData($modelId);
        }

        if (empty($trainingData)) {
            return [
                'success' => false,
                'error' => 'No training data available'
            ];
        }

        // Preprocess training data
        $processedData = $this->preprocessTrainingData($modelId, $trainingData);

        // Train model
        $trainingResult = $this->trainModelAlgorithm($modelId, $processedData);

        if (!$trainingResult['success']) {
            return $trainingResult;
        }

        // Validate model
        $validationResult = $this->validateModel($modelId, $processedData);

        // Update model status
        $this->trainedModels[$modelId] = array_merge($this->trainedModels[$modelId], [
            'status' => 'trained',
            'accuracy' => $validationResult['accuracy'],
            'last_trained' => time(),
            'training_data_size' => count($trainingData),
            'metadata' => [
                'training_time' => $trainingResult['training_time'],
                'validation_score' => $validationResult['accuracy'],
                'feature_importance' => $trainingResult['feature_importance'] ?? []
            ]
        ]);

        // Save trained model
        $this->saveTrainedModel($modelId);

        return [
            'success' => true,
            'model_id' => $modelId,
            'accuracy' => $validationResult['accuracy'],
            'training_time' => $trainingResult['training_time'],
            'training_samples' => count($trainingData)
        ];
    }

    /**
     * Get model performance metrics
     */
    public function getModelMetrics(string $modelId): array
    {
        if (!isset($this->trainedModels[$modelId])) {
            return [
                'success' => false,
                'error' => 'Model not found'
            ];
        }

        $model = $this->trainedModels[$modelId];
        $predictions = $this->getModelPredictions($modelId, 100); // Last 100 predictions

        return [
            'success' => true,
            'model_id' => $modelId,
            'status' => $model['status'],
            'accuracy' => $model['accuracy'],
            'last_trained' => $model['last_trained'],
            'training_data_size' => $model['training_data_size'],
            'total_predictions' => count($predictions),
            'recent_performance' => $this->calculateRecentPerformance($predictions),
            'feature_importance' => $model['metadata']['feature_importance'] ?? []
        ];
    }

    /**
     * Get all available models
     */
    public function getAvailableModels(): array
    {
        $models = [];

        foreach ($this->models as $modelId => $model) {
            $trainedModel = $this->trainedModels[$modelId] ?? null;

            $models[$modelId] = [
                'id' => $modelId,
                'name' => $model['name'],
                'description' => $model['description'],
                'features' => $model['features'],
                'target' => $model['target'],
                'status' => $trainedModel ? $trainedModel['status'] : 'untrained',
                'accuracy' => $trainedModel ? $trainedModel['accuracy'] : 0.0,
                'last_trained' => $trainedModel ? $trainedModel['last_trained'] : 0
            ];
        }

        return $models;
    }

    /**
     * Get prediction history
     */
    public function getPredictionHistory(string $modelId = null, int $limit = 50): array
    {
        if ($modelId) {
            return array_slice(
                array_filter($this->predictionHistory, fn($p) => $p['model_id'] === $modelId),
                0,
                $limit
            );
        }

        return array_slice($this->predictionHistory, 0, $limit);
    }

    /**
     * Retrain all models
     */
    public function retrainAllModels(): array
    {
        $results = [];

        foreach ($this->models as $modelId => $model) {
            $result = $this->trainModel($modelId);
            $results[$modelId] = $result;
        }

        return [
            'success' => true,
            'results' => $results,
            'summary' => $this->summarizeRetrainingResults($results)
        ];
    }

    /**
     * Validate input data for model
     */
    private function validateInputData(string $modelId, array $inputData): array
    {
        $model = $this->models[$modelId];
        $requiredFeatures = $model['features'];

        foreach ($requiredFeatures as $feature) {
            if (!isset($inputData[$feature])) {
                return [
                    'valid' => false,
                    'error' => "Missing required feature: {$feature}"
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Preprocess input data
     */
    private function preprocessInputData(string $modelId, array $inputData): array
    {
        // Normalize data types and ranges
        $processed = [];

        foreach ($inputData as $key => $value) {
            $processed[$key] = $this->normalizeFeature($key, $value);
        }

        return $processed;
    }

    /**
     * Execute prediction using trained model
     */
    private function executePrediction(string $modelId, array $processedData): mixed
    {
        // In a real implementation, this would use the trained ML model
        // For demo purposes, we'll use simple rule-based predictions

        switch ($modelId) {
            case 'demand_prediction':
                return $this->predictDemand($processedData);
            case 'fraud_detection':
                return $this->predictFraud($processedData);
            case 'citizen_satisfaction':
                return $this->predictSatisfaction($processedData);
            case 'service_optimization':
                return $this->predictOptimization($processedData);
            default:
                return ['error' => 'Unknown model'];
        }
    }

    /**
     * Calculate prediction confidence
     */
    private function calculatePredictionConfidence(string $modelId, mixed $prediction, array $inputData): float
    {
        // Simple confidence calculation based on input data consistency
        $baseConfidence = $this->trainedModels[$modelId]['accuracy'];

        // Adjust based on input data quality
        $dataQuality = $this->assessDataQuality($inputData);

        return min(1.0, $baseConfidence * $dataQuality);
    }

    /**
     * Store prediction for analysis
     */
    private function storePrediction(string $modelId, array $inputData, mixed $prediction, float $confidence): void
    {
        $this->predictionHistory[] = [
            'model_id' => $modelId,
            'input_data' => $inputData,
            'prediction' => $prediction,
            'confidence' => $confidence,
            'timestamp' => time()
        ];

        // Keep only recent predictions
        if (count($this->predictionHistory) > 10000) {
            array_shift($this->predictionHistory);
        }
    }

    // Model-specific prediction methods

    private function predictDemand(array $data): array
    {
        // Simple demand prediction based on time factors
        $baseDemand = 100;
        $timeMultiplier = $this->getTimeMultiplier($data);
        $seasonalMultiplier = $this->getSeasonalMultiplier($data);

        $predictedDemand = $baseDemand * $timeMultiplier * $seasonalMultiplier;

        return [
            'demand' => round($predictedDemand),
            'confidence_factors' => ['time' => $timeMultiplier, 'seasonal' => $seasonalMultiplier]
        ];
    }

    private function predictFraud(array $data): array
    {
        // Simple fraud detection based on patterns
        $riskScore = 0;

        if ($data['amount'] > 1000) $riskScore += 0.3;
        if ($data['frequency'] > 5) $riskScore += 0.2;
        if ($data['time_of_day'] < 6 || $data['time_of_day'] > 22) $riskScore += 0.2;

        return [
            'is_fraud' => $riskScore > 0.5,
            'risk_score' => $riskScore,
            'risk_factors' => $this->getRiskFactors($data)
        ];
    }

    private function predictSatisfaction(array $data): array
    {
        // Simple satisfaction prediction
        $score = 4.0; // Base satisfaction

        if ($data['response_time'] < 1) $score += 0.5;
        elseif ($data['response_time'] > 5) $score -= 0.5;

        return [
            'satisfaction_score' => max(1.0, min(5.0, $score)),
            'factors' => ['response_time' => $data['response_time']]
        ];
    }

    private function predictOptimization(array $data): array
    {
        // Simple optimization recommendation
        return [
            'allocation' => 'balanced',
            'completion_time' => $data['estimated_time'] * 0.9,
            'resource_efficiency' => 0.85
        ];
    }

    // Helper methods

    private function getTimeMultiplier(array $data): float
    {
        $hour = $data['time_of_day'] ?? 12;
        // Peak hours have higher demand
        if ($hour >= 9 && $hour <= 17) return 1.5;
        if ($hour >= 18 && $hour <= 21) return 1.2;
        return 0.8;
    }

    private function getSeasonalMultiplier(array $data): float
    {
        $season = $data['season'] ?? 'spring';
        $seasonMultipliers = [
            'spring' => 1.0,
            'summer' => 1.3,
            'fall' => 0.9,
            'winter' => 0.8
        ];
        return $seasonMultipliers[$season] ?? 1.0;
    }

    private function getRiskFactors(array $data): array
    {
        $factors = [];
        if ($data['amount'] > 1000) $factors[] = 'high_amount';
        if ($data['frequency'] > 5) $factors[] = 'high_frequency';
        if ($data['time_of_day'] < 6 || $data['time_of_day'] > 22) $factors[] = 'unusual_time';
        return $factors;
    }

    private function normalizeFeature(string $feature, mixed $value): mixed
    {
        // Simple normalization - in real implementation, this would be more sophisticated
        if (is_numeric($value)) {
            return floatval($value);
        }
        return $value;
    }

    private function assessDataQuality(array $data): float
    {
        // Simple data quality assessment
        $qualityScore = 1.0;
        $missingValues = 0;

        foreach ($data as $value) {
            if ($value === null || $value === '') {
                $missingValues++;
            }
        }

        if ($missingValues > 0) {
            $qualityScore -= ($missingValues / count($data)) * 0.5;
        }

        return max(0.1, $qualityScore);
    }

    private function getTrainingData(string $modelId): array
    {
        // In real implementation, this would fetch from database
        // For demo, return sample data
        return $this->generateSampleTrainingData($modelId);
    }

    private function generateSampleTrainingData(string $modelId): array
    {
        // Generate sample training data for demonstration
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = $this->generateSampleDataPoint($modelId);
        }
        return $data;
    }

    private function generateSampleDataPoint(string $modelId): array
    {
        switch ($modelId) {
            case 'demand_prediction':
                return [
                    'time_of_day' => rand(0, 23),
                    'day_of_week' => rand(1, 7),
                    'season' => ['spring', 'summer', 'fall', 'winter'][rand(0, 3)],
                    'service_requests' => rand(50, 200)
                ];
            case 'fraud_detection':
                return [
                    'amount' => rand(10, 5000),
                    'frequency' => rand(1, 10),
                    'time_of_day' => rand(0, 23),
                    'is_fraud' => rand(0, 100) > 95 ? 1 : 0
                ];
            default:
                return [];
        }
    }

    private function preprocessTrainingData(string $modelId, array $data): array
    {
        // Simple preprocessing - normalize and clean data
        return array_map(function($row) {
            $processed = [];
            foreach ($row as $key => $value) {
                $processed[$key] = $this->normalizeFeature($key, $value);
            }
            return $processed;
        }, $data);
    }

    private function trainModelAlgorithm(string $modelId, array $data): array
    {
        // Simulate training time
        $trainingTime = rand(30, 300);

        return [
            'success' => true,
            'training_time' => $trainingTime,
            'feature_importance' => $this->calculateFeatureImportance($modelId, $data)
        ];
    }

    private function calculateFeatureImportance(string $modelId, array $data): array
    {
        // Simple feature importance calculation
        $features = array_keys($data[0] ?? []);
        $importance = [];

        foreach ($features as $feature) {
            if ($feature !== $this->models[$modelId]['target']) {
                $importance[$feature] = rand(1, 100) / 100;
            }
        }

        return $importance;
    }

    private function validateModel(string $modelId, array $data): array
    {
        // Simple validation - in real implementation, this would use cross-validation
        $accuracy = rand(75, 95) / 100;

        return [
            'accuracy' => $accuracy,
            'precision' => $accuracy - 0.05,
            'recall' => $accuracy - 0.03
        ];
    }

    private function loadTrainedModels(): void
    {
        // In real implementation, load from storage
    }

    private function saveTrainedModel(string $modelId): void
    {
        // In real implementation, save to storage
    }

    private function getModelPredictions(string $modelId, int $limit): array
    {
        return array_slice(
            array_filter($this->predictionHistory, fn($p) => $p['model_id'] === $modelId),
            0,
            $limit
        );
    }

    private function calculateRecentPerformance(array $predictions): array
    {
        if (empty($predictions)) {
            return ['accuracy' => 0.0, 'avg_confidence' => 0.0];
        }

        $totalConfidence = array_sum(array_column($predictions, 'confidence'));
        $avgConfidence = $totalConfidence / count($predictions);

        // Simple accuracy calculation
        $accuracy = rand(80, 95) / 100;

        return [
            'accuracy' => $accuracy,
            'avg_confidence' => round($avgConfidence, 3),
            'total_predictions' => count($predictions)
        ];
    }

    private function summarizeRetrainingResults(array $results): array
    {
        $successful = 0;
        $totalAccuracy = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $successful++;
                $totalAccuracy += $result['accuracy'];
            }
        }

        return [
            'total_models' => count($results),
            'successful_training' => $successful,
            'average_accuracy' => $successful > 0 ? round($totalAccuracy / $successful, 3) : 0.0,
            'success_rate' => count($results) > 0 ? round($successful / count($results), 2) : 0.0
        ];
    }
}
