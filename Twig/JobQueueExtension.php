<?php

namespace JMS\JobQueueBundle\Twig;

use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class JobQueueExtension extends \Twig_Extension
{
    public function __construct(private readonly array $linkGenerators = [])
    {
    }

    public function getTests()
    {
        return [new TwigTest('jms_job_queue_linkable', $this->isLinkable(...))];
    }

    public function getFunctions()
    {
        return [new TwigFunction('jms_job_queue_path', $this->generatePath(...), ['is_safe' => ['html' => true]])];
    }

    public function getFilters()
    {
        return [new TwigFilter('jms_job_queue_linkname', $this->getLinkname(...)), new TwigFilter('jms_job_queue_args', $this->formatArgs(...))];
    }

    public function formatArgs(array $args, $maxLength = 60)
    {
        $str = '';
        $first = true;
        foreach ($args as $arg) {
            $argLength = strlen((string) $arg);

            if ( ! $first) {
                $str .= ' ';
            }
            $first = false;

            if (strlen($str) + $argLength > $maxLength) {
                $str .= substr((string) $arg, 0, $maxLength - strlen($str) - 4).'...';
                break;
            }

            $str .= escapeshellarg((string) $arg);
        }

        return $str;
    }

    public function isLinkable($entity)
    {
        foreach ($this->linkGenerators as $generator) {
            if ($generator->supports($entity)) {
                return true;
            }
        }

        return false;
    }

    public function generatePath($entity)
    {
        foreach ($this->linkGenerators as $generator) {
            if ($generator->supports($entity)) {
                return $generator->generate($entity);
            }
        }

        throw new \RuntimeException(sprintf('The entity "%s" has no link generator.', $entity::class));
    }

    public function getLinkname($entity)
    {
        foreach ($this->linkGenerators as $generator) {
            if ($generator->supports($entity)) {
                return $generator->getLinkname($entity);
            }
        }

        throw new \RuntimeException(sprintf('The entity "%s" has no link generator.', $entity::class));
    }

    public function getName()
    {
        return 'jms_job_queue';
    }
}