<?php

namespace Supra\AuditLog\Writer;

/**
 * Null audit log writer
 * 
 */
class NullAuditLogWriter extends AuditLogWriterAbstraction
{

	/**
	 * Log writer class name
	 * @var string
	 */
	protected static $logWriterClassName = 'Supra\Log\Writer\NullWriter';

}
