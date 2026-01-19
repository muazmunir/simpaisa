<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSslCertificate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simpaisa:generate-ssl-certificate 
                            {--output-dir= : Directory to save the certificate (default: storage/app/ssl)}
                            {--key-name=client : Base name for the certificate files}
                            {--days=365 : Certificate validity in days}
                            {--common-name= : Common Name (CN) for the certificate}
                            {--country=PK : Country code (C)}
                            {--state= : State or Province (ST)}
                            {--city= : City (L)}
                            {--organization= : Organization (O)}
                            {--organizational-unit= : Organizational Unit (OU)}
                            {--email= : Email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate self-signed SSL certificate for mutual SSL authentication with Simpaisa';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outputDir = $this->option('output-dir') ?: storage_path('app/ssl');
        $keyName = $this->option('key-name') ?: 'client';
        $days = (int) $this->option('days');

        $privateKeyPath = $outputDir . '/' . $keyName . '_key.pem';
        $certificatePath = $outputDir . '/' . $keyName . '_cert.pem';
        $publicKeyPath = $outputDir . '/' . $keyName . '_public_key.pem';

        // Create directory if it doesn't exist
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->info("Created directory: {$outputDir}");
        }

        $this->info('Generating SSL certificate for mutual SSL authentication...');
        $this->newLine();

        // Check if OpenSSL is available
        if (!extension_loaded('openssl')) {
            $this->error('OpenSSL extension is not loaded. Please enable it in your PHP configuration.');
            return 1;
        }

        // Prepare certificate subject
        $commonName = $this->option('common-name') ?: $this->ask('Common Name (CN)', 'localhost');
        $country = $this->option('country') ?: 'PK';
        $state = $this->option('state') ?: $this->ask('State/Province', '');
        $city = $this->option('city') ?: $this->ask('City', '');
        $organization = $this->option('organization') ?: $this->ask('Organization', '');
        $organizationalUnit = $this->option('organizational-unit') ?: $this->ask('Organizational Unit', '');
        $email = $this->option('email') ?: $this->ask('Email', '');

        $dn = [
            "countryName" => $country,
            "stateOrProvinceName" => $state,
            "localityName" => $city,
            "organizationName" => $organization,
            "organizationalUnitName" => $organizationalUnit,
            "commonName" => $commonName,
            "emailAddress" => $email,
        ];

        // Remove empty values
        $dn = array_filter($dn, fn($value) => !empty($value));

        $this->info('Generating private key and certificate...');

        // Generate private key
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        // Clear any previous OpenSSL errors
        while (openssl_error_string() !== false) {
            // Clear errors
        }

        $privateKeyResource = @openssl_pkey_new($config);

        if ($privateKeyResource === false) {
            $errors = [];
            while (($error = openssl_error_string()) !== false) {
                $errors[] = $error;
            }
            $errorMessage = !empty($errors) ? implode('; ', $errors) : 'Unknown OpenSSL error';
            $this->error('Failed to generate private key: ' . $errorMessage);
            $this->warn('Note: On Windows, you may need to configure OpenSSL properly.');
            $this->warn('Alternative: Use OpenSSL command line to generate certificate manually.');
            $this->newLine();
            $this->line('Manual generation command:');
            $this->line('  openssl req -x509 -newkey rsa:2048 -keyout ' . basename($privateKeyPath) . ' -out ' . basename($certificatePath) . ' -sha256 -days ' . $days);
            return 1;
        }

        // Generate certificate signing request (CSR)
        $csr = @openssl_csr_new($dn, $privateKeyResource, $config);

        if ($csr === false) {
            openssl_free_key($privateKeyResource);
            $errors = [];
            while (($error = openssl_error_string()) !== false) {
                $errors[] = $error;
            }
            $this->error('Failed to generate CSR: ' . implode('; ', $errors));
            return 1;
        }

        // Generate self-signed certificate
        $certificate = @openssl_csr_sign($csr, null, $privateKeyResource, $days, $config, time());

        if ($certificate === false) {
            openssl_free_key($privateKeyResource);
            openssl_csr_free($csr);
            $errors = [];
            while (($error = openssl_error_string()) !== false) {
                $errors[] = $error;
            }
            $this->error('Failed to generate certificate: ' . implode('; ', $errors));
            return 1;
        }

        // Export private key
        openssl_pkey_export($privateKeyResource, $privateKey);

        // Export certificate
        openssl_x509_export($certificate, $certificatePem);

        // Get public key from certificate
        $publicKeyDetails = openssl_pkey_get_details($privateKeyResource);
        $publicKey = $publicKeyDetails['key'];

        // Save files
        if (File::put($privateKeyPath, $privateKey) === false) {
            $this->error('Failed to save private key');
            return 1;
        }

        if (File::put($certificatePath, $certificatePem) === false) {
            $this->error('Failed to save certificate');
            return 1;
        }

        if (File::put($publicKeyPath, $publicKey) === false) {
            $this->error('Failed to save public key');
            return 1;
        }

        // Set proper permissions
        chmod($privateKeyPath, 0600);
        chmod($certificatePath, 0644);
        chmod($publicKeyPath, 0644);

        // Clean up
        openssl_free_key($privateKeyResource);
        openssl_csr_free($csr);
        openssl_x509_free($certificate);

        $this->info('✓ Private key generated: ' . $privateKeyPath);
        $this->info('✓ Certificate generated: ' . $certificatePath);
        $this->info('✓ Public key generated: ' . $publicKeyPath);
        $this->newLine();

        // Display certificate information
        $certInfo = openssl_x509_parse($certificatePem);
        $this->info('Certificate Details:');
        $this->line('  - Subject: ' . ($certInfo['name'] ?? 'N/A'));
        $this->line('  - Common Name: ' . ($certInfo['subject']['CN'] ?? 'N/A'));
        $this->line('  - Valid From: ' . date('Y-m-d H:i:s', $certInfo['validFrom_time_t']));
        $this->line('  - Valid To: ' . date('Y-m-d H:i:s', $certInfo['validTo_time_t']));
        $this->line('  - Key Size: 2048 bits');
        $this->line('  - Hash Algorithm: SHA-256');
        $this->newLine();

        $this->warn('IMPORTANT:');
        $this->line('1. Keep your private key secure and never share it');
        $this->line('2. Share your certificate with Simpaisa for mutual SSL setup');
        $this->line('3. Update your .env file with the certificate paths:');
        $this->line('   SIMPaisa_SSL_CLIENT_CERT_PATH=' . $certificatePath);
        $this->line('   SIMPaisa_SSL_CLIENT_KEY_PATH=' . $privateKeyPath);
        $this->newLine();

        $this->info('SSL certificate generated successfully!');

        return 0;
    }
}
