#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use League\Csv\Reader;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

$console = new Application('Example CLI', '1.0');

/**
 * List - Files.
 */
$console->register('csv_to_mysql')
        ->setDescription('List all the files in a given directory.')
        ->setDefinition(
                [
                        new InputArgument('csvPath', InputArgument::REQUIRED, 'Path to the CSV file'),
                ]
        )
        ->setCode(function (InputInterface $input, OutputInterface $output) {
                $csvPath = $input->getArgument('csvPath');
                if (! file_exists($csvPath)) {
                        throw new InvalidArgumentException('The csv file must exist.');
                }
                $csv = Reader::createFromPath('./csvFile.csv', 'r');
                $csv->setHeaderOffset(0); // Skip header row
                $records = $csv->getRecords();

                //connect to database
                $dsn = "mysql:host=localhost;dbname=csv_import_db;charset=utf8mb4";
                $username = "ilias";
                try {
                        // Connect to MySQL with PDO
                        $conn = new PDO($dsn, $username, '', [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        ]);

                        $output->writeln('Connected to the database successfully.');

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
                );";
                $conn->exec($createTableSQL);
                $output->writeln("Table Created Successfully");
                
                $conn->beginTransaction();
                $startTime = microtime(true);

                $rowCount = 0;
                $preparedQuery = $conn->prepare("INSERT INTO customers(customer_id, first_name, last_name, company, city, country, phone_1, phone_2, email, subscription_date, website) VALUES(:customer_id, :first_name, :last_name, :company, :city, :country, :phone_1, :phone_2, :email, :subscription_date, :website);");
                foreach ($records as $record) {
                        $preparedQuery->bindParam("customer_id",$record["Customer Id"]);
                        $preparedQuery->bindParam("first_name",$record["First Name"]);
                        $preparedQuery->bindParam("last_name",$record["Last Name"]);
                        $preparedQuery->bindParam("company",$record["Company"]);
                        $preparedQuery->bindParam("city",$record["City"]);
                        $preparedQuery->bindParam("country",$record["Country"]);
                        $preparedQuery->bindParam("phone_1",$record["Phone 1"]);
                        $preparedQuery->bindParam("phone_2",$record["Phone 2"]);
                        $preparedQuery->bindParam("email",$record["Email"]);
                        $preparedQuery->bindParam("subscription_date",$record["Subscription Date"]);
                        $preparedQuery->bindParam("website",$record["Website"]);
                        $preparedQuery->execute();
                        $rowCount++;
                }
                $conn->commit();
                $executionTime = round(microtime(true) - $startTime, 2);
                $output->writeln("Imported ".$rowCount."rows in ".$executionTime." Seconds");

                } catch (PDOException $e) {
                        $conn->rollBack();
                        $output->writeln("Database error: " . $e->getMessage());
                } catch (\Exception $e) {
                        $output->writeln("Error: " . $e->getMessage());
                }
        });

$console->run();

/**
 * Usage:
 *
 * php console.php say:hello
 * php console.php say:hello Foobar
 *
 * php console.php files:list ~/Downloads
 * php console.php files:list ~/Downloads --ext=php
 */
