<?php

namespace FluidGraph;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Command to generate a new migration file
 */
class MigrationGenerateCommand extends Command
{
	/**
	 * @var string
	 */
	protected static $defaultName = 'graph:migrations:generate';

	/**
	 * Constructor with Configuration dependency injection
	 */
	public function __construct(
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
			->setDescription('Generate a new migration file')
			->addArgument('description', InputArgument::OPTIONAL, 'Description of the migration', NULL)
		;
	}

	/**
	 * Execute the command
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$description = $input->getArgument('description');
		$namespace   = $this->config->getNamespace();
		$directory   = $this->config->getPath();
		$timestamp   = date('YmdHis');
		$class       = 'Version' . $timestamp;

		if (!is_dir($directory)) {
			$output->writeln(sprintf('Cannot find migrations directory: %s', $directory));
			return Command::FAILURE;
		}

		$content  = $this->generateMigrationTemplate($namespace, $class, $description);
		$filename = $directory . '/' . $class . '.php';

		if (file_put_contents($filename, $content) !== false) {
			$output->writeln(sprintf('Generated new migration: %s', $filename));
			return Command::SUCCESS;

		} else {
			$output->writeln(sprintf('Failed to generate migration: %s', $filename));
			return Command::FAILURE;

		}
	}

	/**
	 * Generate the migration template content
	 */
	private function generateMigrationTemplate(string $namespace, string $class, ?string $description): string
	{
		return <<<PHP
<?php

namespace $namespace;

use FluidGraph\Graph;
use FluidGraph\Migration;

/**
 * $description
 */
final class $class extends Migration
{
	public function getDescription(): string
	{
		return "$description";
	}

	public function up(Graph \$graph): void
	{
		// Implement migration up logic here
	}

	public function down(Graph \$graph): void
	{
		// Implement migration down logic here
	}
}
PHP;
	}
}
