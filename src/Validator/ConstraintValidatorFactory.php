<?php
namespace Azura\Validator;

use Psr\Container\ContainerInterface;
use Slim\CallableResolverAwareTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;

class ConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    use CallableResolverAwareTrait;

    /**
     * @var array
     */
    protected $validators = [];

    /**
     * @var ContainerInterface
     */
    protected $di;

    /**
     * @param ContainerInterface $di
     */
    public function __construct(ContainerInterface $di)
    {
        $this->di = $di;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(Constraint $constraint)
    {
        $className = $constraint->validatedBy();

        if (!isset($this->validators[$className])) {
            if ('validator.expression' === $className) {
                $this->validators[$className] = new ExpressionValidator;
            } else {
                $this->validators[$className] = $this->resolveCallable($className);
            }
        }

        return $this->validators[$className];
    }
}
