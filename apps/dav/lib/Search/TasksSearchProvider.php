<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\Search;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use Sabre\VObject\Component;

class TasksSearchProvider extends ACalendarSearchProvider {

	/**
	 * @var string[]
	 */
	private static $searchProperties = [
		'SUMMARY',
		'DESCRIPTION',
	];

	/** @var string */
	private static $componentType = 'VTODO';

	/**
	 * @var string[]
	 */
	private static $searchParameters = [];

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'dav-tasks';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Tasks');
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('tasks', $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$principalUri = 'principals/users/' . $user->getUID();
		$calendarsById = $this->getSortedCalendars($principalUri);
		$subscriptionsById = $this->getSortedSubscriptions($principalUri);

		$searchResults = $this->backend->searchPrincipalUri(
			$principalUri,
			$query->getTerm(),
			[self::$componentType],
			self::$searchProperties,
			self::$searchParameters,
			[
				'limit' => $query->getLimit(),
				'offset' => $query->getCursor(),
			]
		);
		$formattedResults = \array_map(function(array $taskRow) use ($calendarsById, $subscriptionsById):TasksSearchResultEntry {
			$thumbnailUrl = $this->urlGenerator->imagePath('tasks', 'tasks.svg');

			$component = $this->getPrimaryComponent($taskRow['calendardata'], self::$componentType);
			$title = (string)$component->SUMMARY ?? $this->l10n->t('Untitled task');
			$subline = $this->generateSubline($component);

			if ($taskRow['calendartype'] === CalDavBackend::CALENDAR_TYPE_CALENDAR) {
				$calendar = $calendarsById[$taskRow['calendarid']];
			} else {
				$calendar = $subscriptionsById[$taskRow['calendarid']];
			}
			$resourceUrl = $this->getDeepLinkToTasksApp($calendar['uri'], $taskRow['uri']);

			return new TasksSearchResultEntry($thumbnailUrl, $title, $subline, $resourceUrl);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$query->getCursor() + count($formattedResults)
		);
	}

	/**
	 * @param string $calendarUri
	 * @param string $taskUid
	 * @return string
	 */
	private function getDeepLinkToTasksApp(string $calendarUri, string $taskUid): string {
//		return $this->urlGenerator->getAbsoluteURL(
//			$this->urlGenerator->linkToRoute('tasks.page.index')
//		);
		return '';
	}

	private function generateSubline(Component $taskComponent): string {
		if ($taskComponent->COMPLETED) {
			// If the event is completed, display Completed at 20.08.20
			return '';
		}

		if ($taskComponent->DUE) {
			// If the event has a due date, display: Due on 20.08.20
			return '';
		}

		return '';
	}
}
