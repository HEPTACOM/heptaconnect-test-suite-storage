<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\TestSuite\Storage\Action;

use Heptacom\HeptaConnect\Portal\Base\StorageKey\Contract\PortalNodeKeyInterface;
use Heptacom\HeptaConnect\Portal\Base\StorageKey\RouteKeyCollection;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Create\RouteCreatePayload;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Create\RouteCreatePayloads;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Create\RouteCreateResult;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Create\RouteCreateResults;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Find\RouteFindCriteria;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Find\RouteFindResult;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Get\RouteGetCriteria;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Get\RouteGetResult;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Listing\ReceptionRouteListCriteria;
use Heptacom\HeptaConnect\Storage\Base\Action\Route\Listing\ReceptionRouteListResult;
use Heptacom\HeptaConnect\Storage\Base\Bridge\Contract\StorageFacadeInterface;
use Heptacom\HeptaConnect\TestSuite\Storage\Fixture\Dataset\EntityA;
use Heptacom\HeptaConnect\TestSuite\Storage\Fixture\Dataset\EntityB;
use Heptacom\HeptaConnect\TestSuite\Storage\Fixture\Dataset\EntityC;
use Heptacom\HeptaConnect\TestSuite\Storage\TestCase;

abstract class RouteTestContract extends TestCase
{
    public function testRouteLifecycle(): void
    {
        $portalA = $this->getPortalNodeA();
        $portalB = $this->getPortalNodeB();
        $facade = $this->createStorageFacade();
        $createAction = $facade->getRouteCreateAction();
        $receptionListAction = $facade->getReceptionRouteListAction();
        $findAction = $facade->getRouteFindAction();
        $getAction = $facade->getRouteGetAction();

        $createPayloads = new RouteCreatePayloads();

        foreach ([$portalA, $portalB] as $sourcePortal) {
            foreach ([$portalA, $portalB] as $targetPortal) {
                foreach ([EntityA::class, EntityB::class, EntityC::class] as $entityType) {
                    $createPayloads->push([new RouteCreatePayload($sourcePortal, $targetPortal, $entityType)]);
                }
            }
        }

        $createResults = $createAction->create($createPayloads);

        static::assertSame($createPayloads->count(), $createResults->count());

        $testCreateResults = new RouteCreateResults($createResults);

        /** @var RouteCreatePayload $createPayload */
        foreach ($createPayloads as $createPayload) {
            $findCriteria = new RouteFindCriteria($createPayload->getSourcePortalNodeKey(), $createPayload->getTargetPortalNodeKey(), $createPayload->getEntityType());
            $findResult = $findAction->find($findCriteria);

            static::assertNotNull($findResult);
            /* @var RouteFindResult $findResult */

            static::assertCount(1, \iterable_to_array($testCreateResults->filter(
                static fn (RouteCreateResult $r): bool => $r->getRouteKey()->equals($findResult->getRouteKey())
            )));
            $testCreateResults = new RouteCreateResults($testCreateResults->filter(
                static fn (RouteCreateResult $r): bool => !$r->getRouteKey()->equals($findResult->getRouteKey())
            ));
            /** @var RouteGetResult[] $getResults */
            $getResults = \iterable_to_array($getAction->get(new RouteGetCriteria(new RouteKeyCollection([$findResult->getRouteKey()]))));

            static::assertCount(1, $getResults);

            foreach ($getResults as $getResult) {
                static::assertTrue($getResult->getRouteKey()->equals($findResult->getRouteKey()));
                static::assertTrue($getResult->getSourcePortalNodeKey()->equals($createPayload->getSourcePortalNodeKey()));
                static::assertTrue($getResult->getTargetPortalNodeKey()->equals($createPayload->getTargetPortalNodeKey()));
                static::assertSame($getResult->getEntityType(), $createPayload->getEntityType());

                /** @var ReceptionRouteListResult[] $listResults */
                $listResults = \iterable_to_array($receptionListAction->list(new ReceptionRouteListCriteria($createPayload->getSourcePortalNodeKey(), $createPayload->getEntityType())));
                $receptionListResult = \array_filter(
                    $listResults,
                    static fn(ReceptionRouteListResult $r): bool => $r->getRouteKey()->equals($findResult->getRouteKey())
                );
                static::assertCount(0, $receptionListResult);
            }
        }
    }

    abstract protected function createStorageFacade(): StorageFacadeInterface;

    abstract protected function getPortalNodeA(): PortalNodeKeyInterface;

    abstract protected function getPortalNodeB(): PortalNodeKeyInterface;
}
