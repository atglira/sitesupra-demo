<?php

namespace Supra\Upgrade\Script;

use Symfony\Component\Console\Output\OutputInterface;
use Supra\Upgrade\UpgradeRunnerAbstraction;
use Supra\Upgrade\Script\UpgradeScriptAbstraction;
use Supra\Console\Output\ArrayOutput;
use Symfony\Component\Console\Application;

class ScriptUpgradeRunner extends UpgradeRunnerAbstraction
{

	const SUPRA_UPGRADE_SUBDIRECTORY = 'supra';
	const UPGRADE_HISTORY_TABLE = 'script_upgrade_history';
	const UPGRADE_PATH = '../upgrade/script';

	/**
	 *
	 * @var Application
	 */
	protected $application;

	/**
	 * @return Application
	 */
	public function getApplication()
	{
		return $this->application;
	}

	/**
	 * @param Application $application 
	 */
	public function setApplication(Application $application)
	{
		$this->application = $application;
	}

	/**
	 * Whether to run the upgrade
	 * @param ScriptUpgradeFile $file
	 * @return boolean
	 */
	protected function allowUpgrade(ScriptUpgradeFile $file)
	{
		$instance = $file->getUpgradeScriptInstance();

		if ( ! ($instance instanceof UpgradeScriptAbstraction)) {
			return false;
		}

		$output = $this->getOutput();
		$application = $this->getApplication();

		$instance->setOutput($output);
		$instance->setApplication($application);

		return $instance->validate();
	}

	/**
	 * Runs the upgrade if it's allowed
	 * @param ScriptUpgradeFile $file
	 */
	protected function executePendingUpgrade(ScriptUpgradeFile $file)
	{
		$connection = $this->getConnection();
		$connection->beginTransaction();
		$path = $file->getShortPath();

		try {
			$upgradeScript = $file->getUpgradeScriptInstance();
			
			$this->getOutput()->writeln('Running upgrade script "' . $file->getShortPath() .'".');
			$markAsExecuted = false;
			$upgradeOutput = null;

			try {
				$upgradeOutput = $upgradeScript->upgrade();
				$this->getOutput()->writeln('Done running upgrade script "' . $file->getShortPath() . '".');
				$markAsExecuted = $upgradeScript->markAsExecuted();
			} catch (\Exception $upgradeFailure) {
				if ( ! $upgradeScript instanceof SkippableOnError) {
					throw $upgradeFailure;
				}
				
				$output = $this->getOutput();
				
				$output->writeln("<info>Exception was raised while doing the upgrade '{$file->getShortPath()}'. Will try repeating next time.</info>");
				$output->writeln('<error>' . $upgradeFailure->getMessage() . '</error>');
				\Log::warn("Exception was raised while doing the upgrade '{$file->getShortPath()}'. Will try repeating next time. Exception: " . (string) $upgradeFailure);
				
				$markAsExecuted = false;
			}
			
			if($markAsExecuted) {
				
				$insert = array(
					'filename' => $path,
					'md5sum' => md5($file->getContents()),
					'output' => $upgradeOutput
				);

				$connection->insert(static::UPGRADE_HISTORY_TABLE, $insert);
			}
		} catch (\PDOException $e) {
			
			$connection->rollback();
			$this->log->error("Could not perform upgrade for $path: {$e->getMessage()}");

			throw $e;
		}

		$connection->commit();

		$this->executedUpgrades[] = $file;
	}

	protected function getFileRecursiveIterator()
	{
		return new ScriptFileRecursiveIterator($this->upgradeDir);
	}

}
