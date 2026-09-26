<?php

declare (strict_types=1);
namespace JooosiMailDeps\Doctrine\DBAL\Logging;

use JooosiMailDeps\Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use JooosiMailDeps\Doctrine\DBAL\Driver\Result as ResultInterface;
use JooosiMailDeps\Doctrine\DBAL\Driver\Statement as StatementInterface;
use JooosiMailDeps\Doctrine\DBAL\ParameterType;
use JooosiMailDeps\Psr\Log\LoggerInterface;
final class Statement extends AbstractStatementMiddleware
{
    /** @var array<int,mixed>|array<string,mixed> */
    private array $params = [];
    /** @var array<int,ParameterType>|array<string,ParameterType> */
    private array $types = [];
    /** @internal This statement can be only instantiated by its connection. */
    public function __construct(StatementInterface $statement, private readonly LoggerInterface $logger, private readonly string $sql)
    {
        parent::__construct($statement);
    }
    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->params[$param] = $value;
        $this->types[$param] = $type;
        parent::bindValue($param, $value, $type);
    }
    public function execute(): ResultInterface
    {
        $this->logger->debug('Executing statement: {sql} (parameters: {params}, types: {types})', ['sql' => $this->sql, 'params' => $this->params, 'types' => $this->types]);
        return parent::execute();
    }
}
