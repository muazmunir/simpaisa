<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateRsaKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simpaisa:generate-rsa-keys 
                            {--output-dir= : Directory to save the keys (default: storage/app/keys)}
                            {--key-name=merchant : Base name for the key files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate RSA 2048-bit key pair for Simpaisa API authentication';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outputDir = $this->option('output-dir') ?: storage_path('app/keys');
        $keyName = $this->option('key-name') ?: 'merchant';

        $privateKeyPath = $outputDir . '/' . $keyName . '_private_key.pem';
        $publicKeyPath = $outputDir . '/' . $keyName . '_public_key.pem';

        // Create directory if it doesn't exist
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->info("Created directory: {$outputDir}");
        }

        $this->info('Generating RSA 2048-bit key pair...');
        $this->newLine();

        // Check if OpenSSL is available
        if (!extension_loaded('openssl')) {
            $this->error('OpenSSL extension is not loaded. Please enable it in your PHP configuration.');
            return 1;
        }

        // Generate private key
        $this->info('Generating private key...');
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        // Clear any previous OpenSSL errors
        while (openssl_error_string() !== false) {
            // Clear errors
        }

        $resource = @openssl_pkey_new($config);

        if ($resource === false) {
            $errors = [];
            while (($error = openssl_error_string()) !== false) {
                $errors[] = $error;
            }
            $errorMessage = !empty($errors) ? implode('; ', $errors) : 'Unknown OpenSSL error';
            $this->error('Failed to generate private key: ' . $errorMessage);
            $this->warn('Note: On Windows, you may need to configure OpenSSL properly.');
            $this->warn('Alternative: Use OpenSSL command line to generate keys manually.');
            $this->newLine();
            $this->line('Manual generation commands:');
            $this->line('  openssl genpkey -algorithm RSA -out PRIVATE_KEY.pem -pkeyopt rsa_keygen_bits:2048');
            $this->line('  openssl rsa -in PRIVATE_KEY.pem -pubout -out PUBLIC_KEY.pem');
            return 1;
        }

        // Export private key
        openssl_pkey_export($resource, $privateKey);

        // Get public key
        $publicKeyDetails = openssl_pkey_get_details($resource);
        $publicKey = $publicKeyDetails['key'];

        // Save private key
        if (File::put($privateKeyPath, $privateKey) === false) {
            $this->error('Failed to save private key');
            return 1;
        }

        // Save public key
        if (File::put($publicKeyPath, $publicKey) === false) {
            $this->error('Failed to save public key');
            return 1;
        }

        // Set proper permissions (private key should be readable only by owner)
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        openssl_free_key($resource);

        $this->info('✓ Private key generated: ' . $privateKeyPath);
        $this->info('✓ Public key generated: ' . $publicKeyPath);
        $this->newLine();

        // Display key information
        $keyDetails = openssl_pkey_get_details(openssl_pkey_get_private($privateKey));
        $this->info('Key Details:');
        $this->line('  - Key Type: RSA');
        $this->line('  - Key Size: ' . $keyDetails['bits'] . ' bits');
        $this->line('  - Hash Algorithm: SHA-256');
        $this->newLine();

        $this->warn('IMPORTANT:');
        $this->line('1. Keep your private key secure and never share it');
        $this->line('2. Share your public key with Simpaisa');
        $this->line('3. Update your .env file with the key paths:');
        $this->line('   SIMPaisa_RSA_PRIVATE_KEY_PATH=' . $privateKeyPath);
        $this->line('   SIMPaisa_RSA_PUBLIC_KEY_PATH=' . $publicKeyPath);
        $this->newLine();

        $this->info('RSA key pair generated successfully!');

        return 0;
    }
}
