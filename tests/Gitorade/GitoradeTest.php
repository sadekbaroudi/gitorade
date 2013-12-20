<?php

namespace Sadekbaroudi\Gitorade;

use Sadekbaroudi\Gitorade\Gitorade;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GitoradeTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @covers Sadekbaroudi\Gitorade\Gitorade::__construct
     * @dataProvider initializeProvider
     */
    public function testInitialize($gitCliConfig, $gitWorkingCopy, $branchList)
    {
        // OperationStateManager mock object
        $osm = $this->getMockBuilder('Sadekbaroudi\OperationState\OperationStateManager')
                    ->disableOriginalConstructor()
                    ->getMock();
        
        // GitConfiguration mock object
        $gc = $this->getMockBuilder('Sadekbaroudi\Gitorade\Configuration\Type\GitConfiguration')
                   ->setMethods(array('getConfig'))
                   ->disableOriginalConstructor()
                   ->getMock();
        
        $gc->expects($this->atLeastOnce())->method('getConfig')->will($this->returnValue($gitCliConfig['getConfigReturn']));
        
        // GithubConfiguration mock object
        $ghc = $this->getMockBuilder('Sadekbaroudi\Gitorade\Configuration\Type\GithubConfiguration')
                    ->setMethods(array('getConfig'))
                    ->disableOriginalConstructor()
                    ->getMock();
        
        $ghc->expects($this->atLeastOnce())->method('getConfig')->will($this->returnValue(TRUE));
        
        // GithubClient mock object
        $ghClient = $this->getMockBuilder('Github\Client')
                         ->setMethods(array('authenticate'))
                         ->disableOriginalConstructor()
                         ->getMock();
        
        $ghClient->expects($this->once())->method('authenticate')->will($this->returnValue(TRUE));
        
        // GitBranches mock object
        $gb = $this->getMockBuilder('GitWrapper\GitBranches')
                   ->setMethods(array('all'))
                   ->disableOriginalConstructor()
                   ->getMock();
        
        $gb->expects($this->atLeastOnce())->method('all')->will($this->returnValue($branchList));
        
        // GitWorkingCopy mock object
        $gwc = $this->getMockBuilder('GitWrapper\GitWorkingCopy')
                    ->setMethods(array('isCloned', 'cloneRepository', 'getBranches'))
                    ->disableOriginalConstructor()
                    ->getMock();
        
        $gwc->expects($this->once())->method('isCloned')->will($this->returnValue($gitWorkingCopy['isCloned']));
        $gwc->expects($this->exactly($gitWorkingCopy['cloneCalls']))->method('cloneRepository')->will($this->returnValue(TRUE));
        $gwc->expects($this->once())->method('getBranches')->will($this->returnValue($gb));
        
        // GitWrapper mock object
        $gw = $this->getMockBuilder('GitWrapper\GitWrapper')
                   ->setMethods(array('setPrivateKey', 'workingCopy'))
                   ->disableOriginalConstructor()
                   ->getMock();
        
        $gw->expects($this->exactly($gitCliConfig['setPrivateKeyCalls']))->method('setPrivateKey')->will($this->returnValue(TRUE));
        $gw->expects($this->once())->method('workingCopy')->will($this->returnValue($gwc));
        
        // BranchManager mock object
        $bm = $this->getMockBuilder('Sadekbaroudi\Gitorade\Branches\BranchManager')
                   ->setMethods(array('add'))
                   ->disableOriginalConstructor()
                   ->getMock();
        
        $bm->expects($this->exactly(count($branchList)))->method('add')->will($this->returnValue(TRUE));
        
        /**
         * Note: had to manually pass ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE because
         *       PHPUnit requires all (even optional) parameters are passed in with returnValueMap.
         *       This is lame, because if the API changes in the future, your unit tests fail :/
         */
        $containerGetMap = array(
            array('OperationStateManager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $osm),
            array('GitConfiguration', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $gc),
            array('GithubConfiguration', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $ghc),
            array('GithubClient', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $ghClient),
            array('GitWrapper', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $gw),
            array('BranchManager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $bm),
        );
        
        
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
                          ->setMethods(array('get', 'setParameter'))
                          ->disableOriginalConstructor()
                          ->getMock();
        
        $container->expects($this->exactly(6))->method('get')->will($this->returnValueMap($containerGetMap));
        
        // Setup the main gitorade class
        $gitorade = $this->getMockBuilder('Sadekbaroudi\Gitorade\Gitorade')
                         ->setMethods(array('fetch'))
                         ->disableOriginalConstructor()
                         ->getMock();
        
        $gitorade->expects($this->exactly($gitWorkingCopy['fetchCalls']))->method('fetch')->will($this->returnValue(TRUE));
        $gitorade->setContainer($container);
        $gitorade->initialize();
    }
    
    public function initializeProvider()
    {
        return array(
        	array(
        	    array(
        	        'getConfigReturn' => TRUE, 'setPrivateKeyCalls' => 1
                ),
        	    array(
        	        'isCloned' => TRUE, 'cloneCalls' => 0, 'fetchCalls' => 1
        	    ),
        	    array(
                    'remotes/origin/testBranch1',
                    'remotes/origin/testBranch2',
                ),
            ),
            array(
                array(
                    'getConfigReturn' => FALSE, 'setPrivateKeyCalls' => 0
                ),
                array(
                    'isCloned' => FALSE, 'cloneCalls' => 1, 'fetchCalls' => 0
                ),
                array(
                    'remotes/origin/testBranch3',
                    'remotes/origin/testBranch4',
                ),
            ),
            array(
                array(
                    'getConfigReturn' => FALSE, 'setPrivateKeyCalls' => 0
                ),
                array(
                    'isCloned' => TRUE, 'cloneCalls' => 0, 'fetchCalls' => 1
                ),
                array(
                ),
            ),
        );
    }
    
    
    //TODO: Need to rewrite this test
    /**
     * @dataProvider pullRequestDataProvider
     * @param mixed $pullRequests
     * @param mixed $exceptionName NULL if no exception, string with exception name if it should throw one
     */
    /*
    public function testSubmitPullRequests($pullRequests, $exceptionName = NULL)
    {
        $pullRequestObj = $this->getMockBuilder('Github\Api\PullRequest')
                               ->setMethods(array('create'))
                               ->disableOriginalConstructor()
                               ->getMock();
        
        $createCalls = $this->exactly(count($pullRequests));
        if (!is_null($exceptionName)) {
            $createCalls = $this->any();
        }
        
        $pullRequestObj->expects($createCalls)
                       ->method('create')
                       ->will($this->returnValue('test'));
        
        $githubClient = $this->getMockBuilder('Github\Client')
                             ->setMethods(array('api'))
                             ->disableOriginalConstructor()
                             ->getMock();
        
        $githubClient->expects($this->exactly(count($pullRequests)))
                     ->method('api')
                     ->will($this->returnValue($pullRequestObj));
        
        // Setup the main gitorade class
        $gitorade = $this->getMockBuilder('Sadekbaroudi\Gitorade\Gitorade')
                         ->setMethods(array('getGithubClient'))
                         ->disableOriginalConstructor()
                         ->getMock();
        
        $gitorade->expects($this->exactly(count($pullRequests)))
                 ->method('getGithubClient')
                 ->will($this->returnValue($githubClient));
        
        if (!is_null($exceptionName)) {
            $this->setExpectedException($exceptionName);
        }
        
        $results = $gitorade->submitPullRequests($pullRequests);
        
        $this->assertTrue(count($results) == count($pullRequests), "Result count didn't match \$pullRequests");
    }
    
    public function pullRequestDataProvider()
    {
        return array(
            // 1st call
            array(
                array(
                    array(
                        'user' => 'user',
                        'repo' => 'repo',
                        'prContent' => array(
                            'base' => 'baseBranch',
                            'head' => 'repo:headBranch',
                            'title' => 'title',
                            'body' => 'body',
                        )
                    ),
                    array(
                        'user' => 'user',
                        'repo' => 'repo',
                        'prContent' => array(
                            'base' => 'baseBranch',
                            'head' => 'repo:headBranch',
                            'title' => 'title',
                            'body' => 'body',
                        )
                    )
                ),
            ),
            // 2nd call
            array(
                array(
                    array(
                        'user' => 'user',
                        'repo' => 'repo',
                        'prContent' => array(
                            'base' => 'baseBranch',
                            'head' => 'repo:headBranch',
                            'title' => 'title',
                            'body' => 'body',
                        )
                    )
                ),
            ),
            // Bad call
            array(
                array(
                    array(
                        'user' => 'user',
                        'repo' => 'repo',
                    ),
                ),
                'PHPUnit_Framework_Error_Notice',
            ),
        );
    }
    */
}