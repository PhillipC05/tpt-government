<?php
/**
 * TPT Government Platform - Token Manager
 *
 * Specialized manager for blockchain token operations and management
 */

namespace Core\Blockchain;

use Core\Database;

class TokenManager
{
    /**
     * Token configuration
     */
    private array $config = [
        'enabled' => true,
        'supported_standards' => ['ERC20', 'ERC721', 'ERC1155', 'SPL'],
        'default_standard' => 'ERC20',
        'max_supply_limit' => 1000000000, // 1 billion
        'min_supply_limit' => 1000,
        'burn_enabled' => true,
        'mint_enabled' => true,
        'transfer_fees_enabled' => false,
        'transfer_fee_percentage' => 0.001,
        'airdrop_enabled' => true,
        'vesting_enabled' => true,
        'staking_enabled' => true
    ];

    /**
     * Managed tokens
     */
    private array $tokens = [];

    /**
     * Token holders
     */
    private array $holders = [];

    /**
     * Token transactions
     */
    private array $transactions = [];

    /**
     * Token distributions
     */
    private array $distributions = [];

    /**
     * Vesting schedules
     */
    private array $vestingSchedules = [];

    /**
     * Staking pools
     */
    private array $stakingPools = [];

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
        $this->config = array_merge($this->config, $config);
        $this->initializeTokenManager();
    }

    /**
     * Initialize token manager
     */
    private function initializeTokenManager(): void
    {
        // Load existing tokens
        $this->loadTokens();

        // Initialize token standards
        $this->initializeTokenStandards();

        // Set up token economics
        $this->setupTokenEconomics();

        // Initialize distribution system
        $this->initializeDistributionSystem();
    }

    /**
     * Create new token
     */
    public function createToken(array $tokenData, array $options = []): array
    {
        // Validate token data
        $validation = $this->validateTokenData($tokenData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid token data: ' . $validation['error']
            ];
        }

        $tokenId = uniqid('token_');
        $standard = $options['standard'] ?? $this->config['default_standard'];

        $token = [
            'id' => $tokenId,
            'name' => $tokenData['name'],
            'symbol' => $tokenData['symbol'],
            'description' => $tokenData['description'] ?? '',
            'standard' => $standard,
            'decimals' => $tokenData['decimals'] ?? 18,
            'total_supply' => $tokenData['total_supply'],
            'circulating_supply' => 0,
            'max_supply' => $tokenData['max_supply'] ?? $tokenData['total_supply'],
            'contract_address' => null, // Will be set after deployment
            'creator_address' => $tokenData['creator_address'],
            'network' => $options['network'] ?? 'ethereum',
            'status' => 'draft',
            'is_burnable' => $options['burnable'] ?? $this->config['burn_enabled'],
            'is_mintable' => $options['mintable'] ?? $this->config['mint_enabled'],
            'transfer_fee_enabled' => $options['transfer_fee'] ?? $this->config['transfer_fees_enabled'],
            'transfer_fee_percentage' => $options['transfer_fee_percentage'] ?? $this->config['transfer_fee_percentage'],
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => $tokenData['metadata'] ?? []
        ];

        // Generate token contract
        $contractResult = $this->generateTokenContract($token, $options);
        if (!$contractResult['success']) {
            return $contractResult;
        }

        $token['contract_source'] = $contractResult['contract_source'];
        $token['contract_abi'] = $contractResult['contract_abi'];

        $this->tokens[$tokenId] = $token;
        $this->saveToken($tokenId, $token);

        // Initialize token distribution
        $this->initializeTokenDistribution($tokenId, $tokenData['initial_distribution'] ?? []);

        return [
            'success' => true,
            'token_id' => $tokenId,
            'token' => $token,
            'contract_source' => $token['contract_source'],
            'message' => 'Token created successfully'
        ];
    }

    /**
     * Mint tokens
     */
    public function mintTokens(string $tokenId, string $toAddress, int $amount, array $options = []): array
    {
        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $token = $this->tokens[$tokenId];

        if (!$token['is_mintable']) {
            return [
                'success' => false,
                'error' => 'Token is not mintable'
            ];
        }

        // Check max supply limit
        if ($token['circulating_supply'] + $amount > $token['max_supply']) {
            return [
                'success' => false,
                'error' => 'Minting would exceed maximum supply'
            ];
        }

        // Create mint transaction
        $transactionId = uniqid('mint_');
        $transaction = [
            'id' => $transactionId,
            'token_id' => $tokenId,
            'type' => 'mint',
            'from_address' => '0x0000000000000000000000000000000000000000', // Mint address
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => 0,
            'status' => 'pending',
            'created_at' => time(),
            'options' => $options
        ];

        $this->transactions[$transactionId] = $transaction;
        $this->saveTransaction($transactionId, $transaction);

        // Update token supply
        $this->tokens[$tokenId]['circulating_supply'] += $amount;
        $this->saveToken($tokenId, $this->tokens[$tokenId]);

        // Update holder balance
        $this->updateHolderBalance($tokenId, $toAddress, $amount);

        // Process transaction
        $this->processMintTransaction($transactionId);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'amount_minted' => $amount,
            'new_supply' => $this->tokens[$tokenId]['circulating_supply'],
            'message' => 'Tokens minted successfully'
        ];
    }

    /**
     * Burn tokens
     */
    public function burnTokens(string $tokenId, string $fromAddress, int $amount, array $options = []): array
    {
        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $token = $this->tokens[$tokenId];

        if (!$token['is_burnable']) {
            return [
                'success' => false,
                'error' => 'Token is not burnable'
            ];
        }

        // Check holder balance
        $holderBalance = $this->getHolderBalance($tokenId, $fromAddress);
        if ($holderBalance < $amount) {
            return [
                'success' => false,
                'error' => 'Insufficient token balance'
            ];
        }

        // Create burn transaction
        $transactionId = uniqid('burn_');
        $transaction = [
            'id' => $transactionId,
            'token_id' => $tokenId,
            'type' => 'burn',
            'from_address' => $fromAddress,
            'to_address' => '0x000000000000000000000000000000000000dEaD', // Burn address
            'amount' => $amount,
            'fee' => 0,
            'status' => 'pending',
            'created_at' => time(),
            'options' => $options
        ];

        $this->transactions[$transactionId] = $transaction;
        $this->saveTransaction($transactionId, $transaction);

        // Update token supply
        $this->tokens[$tokenId]['circulating_supply'] -= $amount;
        $this->saveToken($tokenId, $this->tokens[$tokenId]);

        // Update holder balance
        $this->updateHolderBalance($tokenId, $fromAddress, -$amount);

        // Process transaction
        $this->processBurnTransaction($transactionId);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'amount_burned' => $amount,
            'new_supply' => $this->tokens[$tokenId]['circulating_supply'],
            'message' => 'Tokens burned successfully'
        ];
    }

    /**
     * Transfer tokens
     */
    public function transferTokens(string $tokenId, string $fromAddress, string $toAddress, int $amount, array $options = []): array
    {
        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $token = $this->tokens[$tokenId];

        // Check sender balance
        $senderBalance = $this->getHolderBalance($tokenId, $fromAddress);
        if ($senderBalance < $amount) {
            return [
                'success' => false,
                'error' => 'Insufficient token balance'
            ];
        }

        // Calculate transfer fee if enabled
        $transferFee = 0;
        if ($token['transfer_fee_enabled']) {
            $transferFee = intval($amount * $token['transfer_fee_percentage']);
        }

        $totalDeduct = $amount + $transferFee;

        // Create transfer transaction
        $transactionId = uniqid('transfer_');
        $transaction = [
            'id' => $transactionId,
            'token_id' => $tokenId,
            'type' => 'transfer',
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee' => $transferFee,
            'total_amount' => $totalDeduct,
            'status' => 'pending',
            'created_at' => time(),
            'options' => $options
        ];

        $this->transactions[$transactionId] = $transaction;
        $this->saveTransaction($transactionId, $transaction);

        // Update balances
        $this->updateHolderBalance($tokenId, $fromAddress, -$totalDeduct);
        $this->updateHolderBalance($tokenId, $toAddress, $amount);

        // If there's a transfer fee, add it to a fee collection address
        if ($transferFee > 0) {
            $feeAddress = $options['fee_address'] ?? '0x000000000000000000000000000000000000fee';
            $this->updateHolderBalance($tokenId, $feeAddress, $transferFee);
        }

        // Process transaction
        $this->processTransferTransaction($transactionId);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'amount_transferred' => $amount,
            'transfer_fee' => $transferFee,
            'message' => 'Tokens transferred successfully'
        ];
    }

    /**
     * Get token information
     */
    public function getToken(string $tokenId): array
    {
        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $token = $this->tokens[$tokenId];

        return [
            'success' => true,
            'token' => $token,
            'holders_count' => $this->getTokenHoldersCount($tokenId),
            'total_transactions' => $this->getTokenTransactionsCount($tokenId),
            'market_cap' => $this->calculateMarketCap($token),
            'price' => $this->getTokenPrice($tokenId)
        ];
    }

    /**
     * Get token balance for address
     */
    public function getTokenBalance(string $tokenId, string $address): array
    {
        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $balance = $this->getHolderBalance($tokenId, $address);

        return [
            'success' => true,
            'token_id' => $tokenId,
            'address' => $address,
            'balance' => $balance,
            'formatted_balance' => $this->formatTokenAmount($balance, $this->tokens[$tokenId]['decimals']),
            'percentage_of_supply' => $this->tokens[$tokenId]['circulating_supply'] > 0 ?
                ($balance / $this->tokens[$tokenId]['circulating_supply']) * 100 : 0
        ];
    }

    /**
     * Create airdrop
     */
    public function createAirdrop(string $tokenId, array $recipients, array $options = []): array
    {
        if (!$this->config['airdrop_enabled']) {
            return [
                'success' => false,
                'error' => 'Airdrops are disabled'
            ];
        }

        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $token = $this->tokens[$tokenId];
        $airdropId = uniqid('airdrop_');

        // Calculate total airdrop amount
        $totalAmount = array_sum(array_column($recipients, 'amount'));

        // Check if creator has enough tokens
        $creatorBalance = $this->getHolderBalance($tokenId, $token['creator_address']);
        if ($creatorBalance < $totalAmount) {
            return [
                'success' => false,
                'error' => 'Insufficient balance for airdrop'
            ];
        }

        $airdrop = [
            'id' => $airdropId,
            'token_id' => $tokenId,
            'creator_address' => $token['creator_address'],
            'recipients' => $recipients,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'scheduled_at' => $options['scheduled_at'] ?? time(),
            'completed_at' => null,
            'created_at' => time(),
            'options' => $options
        ];

        $this->distributions[$airdropId] = $airdrop;
        $this->saveDistribution($airdropId, $airdrop);

        // Process airdrop if not scheduled for later
        if ($airdrop['scheduled_at'] <= time()) {
            $this->processAirdrop($airdropId);
        }

        return [
            'success' => true,
            'airdrop_id' => $airdropId,
            'total_recipients' => count($recipients),
            'total_amount' => $totalAmount,
            'message' => 'Airdrop created successfully'
        ];
    }

    /**
     * Create vesting schedule
     */
    public function createVestingSchedule(string $tokenId, string $beneficiaryAddress, array $scheduleData): array
    {
        if (!$this->config['vesting_enabled']) {
            return [
                'success' => false,
                'error' => 'Vesting is disabled'
            ];
        }

        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $vestingId = uniqid('vesting_');

        $vesting = [
            'id' => $vestingId,
            'token_id' => $tokenId,
            'beneficiary_address' => $beneficiaryAddress,
            'total_amount' => $scheduleData['total_amount'],
            'start_time' => $scheduleData['start_time'] ?? time(),
            'cliff_period' => $scheduleData['cliff_period'] ?? 0, // seconds
            'vesting_period' => $scheduleData['vesting_period'], // total vesting time in seconds
            'released_amount' => 0,
            'status' => 'active',
            'created_at' => time(),
            'last_release' => null
        ];

        $this->vestingSchedules[$vestingId] = $vesting;
        $this->saveVestingSchedule($vestingId, $vesting);

        return [
            'success' => true,
            'vesting_id' => $vestingId,
            'vesting' => $vesting,
            'message' => 'Vesting schedule created successfully'
        ];
    }

    /**
     * Release vested tokens
     */
    public function releaseVestedTokens(string $vestingId): array
    {
        if (!isset($this->vestingSchedules[$vestingId])) {
            return [
                'success' => false,
                'error' => 'Vesting schedule not found'
            ];
        }

        $vesting = $this->vestingSchedules[$vestingId];

        if ($vesting['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Vesting schedule is not active'
            ];
        }

        $currentTime = time();
        $vestedAmount = $this->calculateVestedAmount($vesting, $currentTime);
        $releasableAmount = $vestedAmount - $vesting['released_amount'];

        if ($releasableAmount <= 0) {
            return [
                'success' => false,
                'error' => 'No tokens available for release'
            ];
        }

        // Transfer tokens to beneficiary
        $transferResult = $this->transferTokens(
            $vesting['token_id'],
            $this->tokens[$vesting['token_id']]['creator_address'], // Assuming tokens are held by creator
            $vesting['beneficiary_address'],
            $releasableAmount,
            ['vesting_release' => true]
        );

        if ($transferResult['success']) {
            $vesting['released_amount'] += $releasableAmount;
            $vesting['last_release'] = $currentTime;

            // Check if fully vested
            if ($vesting['released_amount'] >= $vesting['total_amount']) {
                $vesting['status'] = 'completed';
            }

            $this->vestingSchedules[$vestingId] = $vesting;
            $this->saveVestingSchedule($vestingId, $vesting);
        }

        return $transferResult;
    }

    /**
     * Create staking pool
     */
    public function createStakingPool(string $tokenId, array $poolData): array
    {
        if (!$this->config['staking_enabled']) {
            return [
                'success' => false,
                'error' => 'Staking is disabled'
            ];
        }

        if (!isset($this->tokens[$tokenId])) {
            return [
                'success' => false,
                'error' => 'Token not found'
            ];
        }

        $poolId = uniqid('pool_');

        $pool = [
            'id' => $poolId,
            'token_id' => $tokenId,
            'name' => $poolData['name'],
            'description' => $poolData['description'] ?? '',
            'reward_token_id' => $poolData['reward_token_id'] ?? $tokenId,
            'reward_rate' => $poolData['reward_rate'], // tokens per second
            'min_stake_amount' => $poolData['min_stake_amount'] ?? 1,
            'max_stake_amount' => $poolData['max_stake_amount'] ?? null,
            'lock_period' => $poolData['lock_period'] ?? 0, // seconds
            'total_staked' => 0,
            'total_rewards_distributed' => 0,
            'status' => 'active',
            'created_at' => time(),
            'stakers' => []
        ];

        $this->stakingPools[$poolId] = $pool;
        $this->saveStakingPool($poolId, $pool);

        return [
            'success' => true,
            'pool_id' => $poolId,
            'pool' => $pool,
            'message' => 'Staking pool created successfully'
        ];
    }

    /**
     * Stake tokens
     */
    public function stakeTokens(string $poolId, string $stakerAddress, int $amount): array
    {
        if (!isset($this->stakingPools[$poolId])) {
            return [
                'success' => false,
                'error' => 'Staking pool not found'
            ];
        }

        $pool = $this->stakingPools[$poolId];
        $tokenId = $pool['token_id'];

        // Validate stake amount
        if ($amount < $pool['min_stake_amount']) {
            return [
                'success' => false,
                'error' => 'Stake amount below minimum'
            ];
        }

        if ($pool['max_stake_amount'] && $amount > $pool['max_stake_amount']) {
            return [
                'success' => false,
                'error' => 'Stake amount above maximum'
            ];
        }

        // Check staker balance
        $stakerBalance = $this->getHolderBalance($tokenId, $stakerAddress);
        if ($stakerBalance < $amount) {
            return [
                'success' => false,
                'error' => 'Insufficient token balance'
            ];
        }

        // Transfer tokens to pool
        $transferResult = $this->transferTokens($tokenId, $stakerAddress, 'pool_' . $poolId, $amount, ['staking' => true]);

        if ($transferResult['success']) {
            // Add staker to pool
            $stakerId = $stakerAddress;
            if (!isset($pool['stakers'][$stakerId])) {
                $pool['stakers'][$stakerId] = [
                    'address' => $stakerAddress,
                    'total_staked' => 0,
                    'rewards_earned' => 0,
                    'last_reward_calculation' => time(),
                    'stakes' => []
                ];
            }

            $stakeId = uniqid('stake_');
            $pool['stakers'][$stakerId]['stakes'][$stakeId] = [
                'id' => $stakeId,
                'amount' => $amount,
                'staked_at' => time(),
                'unlock_at' => $pool['lock_period'] > 0 ? time() + $pool['lock_period'] : null,
                'rewards' => 0
            ];

            $pool['stakers'][$stakerId]['total_staked'] += $amount;
            $pool['total_staked'] += $amount;

            $this->stakingPools[$poolId] = $pool;
            $this->saveStakingPool($poolId, $pool);
        }

        return $transferResult;
    }

    /**
     * Get token analytics
     */
    public function getTokenAnalytics(string $tokenId = null): array
    {
        $analytics = [
            'total_tokens' => count($this->tokens),
            'active_tokens' => count(array_filter($this->tokens, fn($t) => $t['status'] === 'active')),
            'total_supply_across_tokens' => $this->calculateTotalSupplyAcrossTokens(),
            'total_holders' => count($this->holders),
            'total_transactions' => count($this->transactions),
            'tokens_by_standard' => $this->getTokensByStandard(),
            'top_tokens_by_holders' => $this->getTopTokensByHolders(),
            'transaction_volume_trends' => $this->getTransactionVolumeTrends()
        ];

        if ($tokenId) {
            $analytics['token_specific'] = $this->getTokenSpecificAnalytics($tokenId);
        }

        return [
            'success' => true,
            'analytics' => $analytics,
            'generated_at' => time()
        ];
    }

    // Private helper methods

    private function validateTokenData(array $data): array
    {
        if (empty($data['name']) || empty($data['symbol']) || !isset($data['total_supply'])) {
            return ['valid' => false, 'error' => 'Name, symbol, and total supply are required'];
        }

        if (strlen($data['name']) < 3 || strlen($data['name']) > 50) {
            return ['valid' => false, 'error' => 'Token name must be between 3 and 50 characters'];
        }

        if (strlen($data['symbol']) < 2 || strlen($data['symbol']) > 10) {
            return ['valid' => false, 'error' => 'Token symbol must be between 2 and 10 characters'];
        }

        if ($data['total_supply'] < $this->config['min_supply_limit'] ||
            $data['total_supply'] > $this->config['max_supply_limit']) {
            return ['valid' => false, 'error' => 'Total supply out of allowed range'];
        }

        // Check for duplicate symbols
        foreach ($this->tokens as $token) {
            if ($token['symbol'] === $data['symbol']) {
                return ['valid' => false, 'error' => 'Token symbol already exists'];
            }
        }

        return ['valid' => true];
    }

    private function generateTokenContract(array $token, array $options): array
    {
        // Generate contract based on standard
        $contractSource = '';
        $contractAbi = [];

        switch ($token['standard']) {
            case 'ERC20':
                $contractSource = $this->generateERC20Contract($token);
                $contractAbi = $this->getERC20ABI();
                break;
            case 'ERC721':
                $contractSource = $this->generateERC721Contract($token);
                $contractAbi = $this->getERC721ABI();
                break;
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported token standard'
                ];
        }

        return [
            'success' => true,
            'contract_source' => $contractSource,
            'contract_abi' => $contractAbi
        ];
    }

    private function generateERC20Contract(array $token): string
    {
        return "// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import \"@openzeppelin/contracts/token/ERC20/ERC20.sol\";
import \"@openzeppelin/contracts/access/Ownable.sol\";

contract {$token['name']}Token is ERC20, Ownable {
    uint256 private _maxSupply = {$token['max_supply']};

    constructor(uint256 initialSupply) ERC20(\"{$token['name']}\", \"{$token['symbol']}\") {
        _mint(msg.sender, initialSupply);
    }

    function mint(address to, uint256 amount) public onlyOwner {
        require(totalSupply() + amount <= _maxSupply, \"Exceeds max supply\");
        _mint(to, amount);
    }

    function burn(uint256 amount) public {
        _burn(msg.sender, amount);
    }

    function maxSupply() public view returns (uint256) {
        return _maxSupply;
    }
}";
    }

    private function generateERC721Contract(array $token): string
    {
        return "// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import \"@openzeppelin/contracts/token/ERC721/ERC721.sol\";
import \"@openzeppelin/contracts/access/Ownable.sol\";

contract {$token['name']}Token is ERC721, Ownable {
    uint256 private _tokenIdCounter;

    constructor() ERC721(\"{$token['name']}\", \"{$token['symbol']}\") {}

    function mint(address to) public onlyOwner {
        uint256 tokenId = _tokenIdCounter;
        _tokenIdCounter++;
        _safeMint(to, tokenId);
    }

    function burn(uint256 tokenId) public {
        require(ownerOf(tokenId) == msg.sender, \"Not token owner\");
        _burn(tokenId);
    }
}";
    }

    private function getERC20ABI(): array
    {
        // Return ERC20 ABI
        return [
            // Standard ERC20 functions
            ['name', 'symbol', 'decimals', 'totalSupply', 'balanceOf', 'transfer', 'approve', 'transferFrom']
        ];
    }

    private function getERC721ABI(): array
    {
        // Return ERC721 ABI
        return [
            // Standard ERC721 functions
            ['balanceOf', 'ownerOf', 'transferFrom', 'approve', 'setApprovalForAll', 'getApproved', 'isApprovedForAll']
        ];
    }

    private function initializeTokenDistribution(string $tokenId, array $distribution): void
    {
        if (!empty($distribution)) {
            $this->distributions[$tokenId . '_initial'] = [
                'id' => $tokenId . '_initial',
                'token_id' => $tokenId,
                'type' => 'initial',
                'recipients' => $distribution,
                'status' => 'completed',
                'created_at' => time()
            ];
        }
    }

    private function updateHolderBalance(string $tokenId, string $address, int $amount): void
    {
        $holderKey = $tokenId . ':' . $address;

        if (!isset($this->holders[$holderKey])) {
            $this->holders[$holderKey] = [
                'token_id' => $tokenId,
                'address' => $address,
                'balance' => 0,
                'first_transaction' => time(),
                'last_transaction' => time()
            ];
        }

        $this->holders[$holderKey]['balance'] += $amount;
        $this->holders[$holderKey]['last_transaction'] = time();

        // Remove holder if balance is zero
        if ($this->holders[$holderKey]['balance'] <= 0) {
            unset($this->holders[$holderKey]);
        } else {
            $this->saveHolder($holderKey, $this->holders[$holderKey]);
        }
    }

    private function getHolderBalance(string $tokenId, string $address): int
    {
        $holderKey = $tokenId . ':' . $address;
        return $this->holders[$holderKey]['balance'] ?? 0;
    }

    private function processMintTransaction(string $transactionId): void
    {
        // Simulate processing
        $this->transactions[$transactionId]['status'] = 'completed';
        $this->transactions[$transactionId]['completed_at'] = time();
        $this->saveTransaction($transactionId, $this->transactions[$transactionId]);
    }

    private function processBurnTransaction(string $transactionId): void
    {
        // Simulate processing
        $this->transactions[$transactionId]['status'] = '
