#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use League\Csv\Reader;

$console = new Application('CSV Import CLI', '1.0');

$console->register('csv_to_mysql')
        ->setDescription('Import CSV data into MySQL')
        ->setDefinition([
                new InputArgument('csvPath', InputArgument::REQUIRED, 'Path to the CSV file'),
        ])
        ->setCode(function (InputInterface $input, OutputInterface $output) {
                $csvPath = $input->getArgument('csvPath');

                if (!file_exists($csvPath)) {
                        throw new InvalidArgumentException("Error: CSV file '$csvPath' does not exist.");
                }

                // Read CSV file
                $csv = Reader::createFromPath($csvPath, 'r');
                $csv->setHeaderOffset(0); // First row as header
                $records = iterator_to_array($csv->getRecords()); // Convert records to array

                if (empty($records)) {
                        throw new InvalidArgumentException("Error: The CSV file is empty.");
                }

                // Connect to MySQL
                $dsn = "mysql:host=localhost;dbname=csv_import_db;charset=utf8mb4";
                $username = "ilias";
                $password = "";

                try {
                        $conn = new PDO($dsn, $username, $password, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        ]);

                        $output->writeln('<info>Connected to the database successfully.</info>');

                        // Create table if not exists
                        createTable($conn, $output);

                        // Insert records
                        $rowCount = insertRecords($conn, $records, $output);

                        $output->writeln("<info>Imported $rowCount rows successfully.</info>");
                } catch (PDOException $e) {
                        $output->writeln("<error>Database error: " . $e->getMessage() . "</error>");
                } catch (\Exception $e) {
                        $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
                }
        });

$console->run();

/**
 * Creates the `customers` table if it does not exist.
 */
function createTable(PDO $conn, OutputInterface $output): void
{
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id CHAR(16) NOT NULL UNIQUE,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            company VARCHAR(100),
            city VARCHAR(50),
            country VARCHAR(100),
            phone_1 VARCHAR(50),
            phone_2 VARCHAR(50),
            email VARCHAR(150) UNIQUE,
            subscription_date DATE NOT NULL,
            website VARCHAR(200)
        );
    ";

        $conn->exec($createTableSQL);
        $output->writeln("<info>Table 'customers' created successfully.</info>");
}

/**
 * Inserts records into the database in a transaction.
 */
function insertRecords(PDO $conn, array $records, OutputInterface $output)
{
        $sql = "INSERT INTO customers (customer_id, first_name, last_name, company, city, country, phone_1, phone_2, email, subscription_date, website)
            VALUES (:customer_id, :first_name, :last_name, :company, :city, :country, :phone_1, :phone_2, :email, :subscription_date, :website)";

        $stmt = $conn->prepare($sql);
        $conn->beginTransaction();

        $startTime = microtime(true);
        $rowCount = 0;

        try {
                foreach ($records as $record) {
                        $stmt->execute([
                                ':customer_id' => $record["Customer Id"],
                                ':first_name' => $record["First Name"],
                                ':last_name' => $record["Last Name"],
                                ':company' => $record["Company"],
                                ':city' => $record["City"],
                                ':country' => $record["Country"],
                                ':phone_1' => $record["Phone 1"],
                                ':phone_2' => $record["Phone 2"],
                                ':email' => $record["Email"],
                                ':subscription_date' => $record["Subscription Date"],
                                ':website' => $record["Website"],
                        ]);
                        $rowCount++;
                }

                $conn->commit();
                $executionTime = round(microtime(true) - $startTime);
                $output->writeln("<info>Imported $rowCount rows in $executionTime seconds.</info>");
        } catch (PDOException $e) {
                $conn->rollBack();
                throw new PDOException("Error during transaction: " . $e->getMessage());
        }
        return $rowCount;
}
