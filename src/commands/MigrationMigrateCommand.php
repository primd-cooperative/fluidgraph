<?php

namespace FluidGraph;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to execute graph migrations
 */
class MigrationMigrateCommand extends Command
{
	/**
	 * @var string
	 */
	protected static $defaultName = 'graph:migrations:migrate';

	/**
	 * Constructor with Graph and Configuration dependency injection
	 */
	public function __construct(
		protected Graph $graph,
		protected Migrations\Configuration $config
	) {
		parent::__construct();
	}

	/**
	 * Configure the command
	 */
	protected function configure(): void
	{
		$this
			->setDescription('Execute graph migrations')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Output the migrations that would be executed without running them');
	}

	/**
	 * Execute the command
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$dry_run   = $input->getOption('dry-run');
		$directory = $this->config->getPath();
		$namespace = $this->config->getNamespace();

		if (!is_dir($directory)) {
			$output->writeln('No migrations directory found. Use graph:migrations:generate to create your first migration.');
			return Command::SUCCESS;
		}

		try {
			$applied_migrations = $this->getAppliedMigrations();

		} catch (Exception $e) {
			$output->writeln(sprintf('Error: %s', $e->getMessage()));
			return Command::FAILURE;
		}

		$migration_files    = $this->getMigrationFiles($directory);
		$pending_migrations = array_diff($migration_files, $applied_migrations);

		if (empty($pending_migrations)) {
			$output->writeln('No pending migrations.');
			return Command::SUCCESS;
		}

		sort($pending_migrations);

		if ($dry_run) {
			$output->writeln('Migrations that would be executed:');

			foreach ($pending_migrations as $migration) {
				$output->writeln("  - $migration");
			}

			return Command::SUCCESS;
		}

		$output->writeln(sprintf('Executing %d migration(s)...', count($pending_migrations)));

		foreach ($pending_migrations as $class) {
			$output->write(sprintf('  > %s... ', $class));

			try {
				include($directory . '/' . $class . '.php');

				$qualified = $namespace . '\\' . $class;
				$migration = new $qualified();

				if (!$migration instanceof Migration) {
					throw new \RuntimeException(sprintf('Class %s does not extend FluidGraph\Migration', $class));
				}

				$this->graph->exec('BEGIN');
				$migration->up($this->graph);
				$this->graph->exec('COMMIT');

				$this->recordMigration($namespace, $class, $migration->getDescription());
				$output->writeln('DONE');

			} catch (\Exception $e) {
				$output->writeln('FAILED');
				$output->writeln(sprintf('Error: %s', $e->getMessage()));

				return Command::FAILURE;
			}
		}

		$output->writeln('Migrations completed successfully.');
		return Command::SUCCESS;
	}


	/**
	 * Get list of applied migrations from graph
	 */
	private function getAppliedMigrations(): array
	{
		return $this->graph
			->exec("
				MATCH (m:Migration)
				RETURN m.class AS class
				ORDER BY m.class
			")
			->unwrap()
		;
	}


	/**
	 * Get all migration files from directory
	 */
	private function getMigrationFiles(string $directory): array
	{
		$files   = [];
		$pattern = $directory . '/Version*.php';

		foreach (glob($pattern) as $file) {
			$files[] = pathinfo($file, PATHINFO_FILENAME);
		}

		return $files;
	}


	/**
	 * Record a migration in the graph
	 */
	private function recordMigration(string $namespace, string $class, string $description): void
	{
		$this->graph
			->query('
				CREATE (m:Migration {
					class: $class,
					namespace: $namespace,
					description: $description,
					executed_at: datetime()
				})
			')
			->setAll(get_defined_vars())
			->run()
		;
	}
}
