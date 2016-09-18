<?php

namespace Youshido\GraphQLBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class GraphQLConfigureCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('graphql:configure')
            ->setDescription('Generates GraphQL Schema class')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'Bundle to generate class to', 'AppBundle')
            ->addOption('composer');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName     = $input->getArgument('bundle');
        $isComposerCall = $input->getOption('composer');
        if (substr($bundleName, -6) != 'Bundle') $bundleName .= 'Bundle';

        $container = $this->getContainer();

        $rootDir       = $container->getParameter('kernel.root_dir');
        $configFile    = $rootDir . '/../app/config/config.yml';
        try {
            $bundle    = $container->get('kernel')->getBundle($bundleName);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('There is no active bundleName: ' . $bundleName);
            return;
        }

        $className          = 'Schema';
        $bundleNameSpace    = $bundle->getNameSpace() . '\\GraphQL';
        $graphqlPath        = $bundle->getPath() . '/GraphQL/';
        $classPath          = $graphqlPath . '/' . $className . '.php';

        $inputHelper = $this->getHelper('question');
        if (file_exists($classPath)) {
            if (!$isComposerCall) {
                $output->writeln(sprintf('Schema class %s was found.', $bundleNameSpace.'\\'.$className));
            }
        } else {
            $question = new ConfirmationQuestion(sprintf('Confirm creating class at %s ? [Y/n]', $bundleNameSpace.'\\'.$className), true);
            if (!$inputHelper->ask($input, $output, $question)) {
                return;
            }

            if (!is_dir($graphqlPath)) {
                mkdir($graphqlPath, 0777, true);
            }
            file_put_contents($classPath, $this->getSchemaClassTemplate($bundleNameSpace, $className));

            $output->writeln('Schema file has been created at');
            $output->writeln($classPath . "\n");

            $originalConfigData = file_get_contents($configFile);
            if (strpos($originalConfigData, 'graph_ql') === false) {
                $configData = <<<CONFIG
graph_ql:
    schema_class: "{$bundleName}\\\\GraphQL\\\\{$className}"


CONFIG;
                file_put_contents($configFile, $configData . $originalConfigData);
            }
        }
        if (!$this->graphQLRouteExists()) {
            $question = new ConfirmationQuestion('Confirm adding GraphQL route? [Y/n]', true);
            $resource = $this->getMainRouteConfig();
            if ($resource && !$inputHelper->ask($input, $output, $question)) {
                $routeConfigData = <<<CONFIG

graphql:
    resource: "@GraphQLBundle/Controller/"
CONFIG;
                file_put_contents($resource, $routeConfigData, FILE_APPEND);
                $output->writeln('Config was added to ' . $resource);
            }
        } else {
            if (!$isComposerCall) {
                $output->writeln('GraphQL default route was found.');
            }
        }
    }

    protected function getMainRouteConfig()
    {
        $routerResources = $this->getContainer()->get('router')->getRouteCollection()->getResources();
        foreach ($routerResources as $resource) {
            /** @var FileResource|DirectoryResource $resource */
            if (substr($resource->getResource(), -11) == 'routing.yml') {
                return $resource->getResource();
            }
        }

        return null;
    }

    protected function graphQLRouteExists()
    {
        $routerResources = $this->getContainer()->get('router')->getRouteCollection()->getResources();
        foreach ($routerResources as $resource) {
            /** @var FileResource|DirectoryResource $resource */
            if (strpos($resource->getResource(), 'GraphQLController.php') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function generateRoutes()
    {

    }

    protected function getSchemaClassTemplate($bundleNameSpace, $className = 'Schema')
    {
        $tpl = <<<TEXT
<?php
/**
 * This class was automatically generated by GraphQL Schema generator
 */

namespace $bundleNameSpace;

use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Type\Scalar\StringType;

class $className extends AbstractSchema
{
    public function build(SchemaConfig \$config)
    {
        \$config->getQuery()->addFields([
            'hello' => [
                'type'    => new StringType(),
                'args'    => [
                    'name' => [
                        'type' => new StringType(),
                        'default' => 'Stranger'
                    ]
                ],
                'resolve' => function (\$context, \$args) {
                    return 'Hello ' . \$args['name'];
                }
            ]
        ]);
    }

}


TEXT;

        return $tpl;
    }

}
