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

class EventsSearchProvider extends ACalendarSearchProvider {

	/**
	 * @var string[]
	 */
	private static $searchProperties = [
		'SUMMARY',
		'LOCATION',
		'DESCRIPTION',
		'ATTENDEE',
		'ORGANIZER',
	];

	/**
	 * @var string[]
	 */
	private static $searchParameters = [
		'ATTENDEE' => ['CN'],
		'ORGANIZER' => ['CN'],
	];

	/** @var string */
	private static $componentType = 'VEVENT';

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'dav-calendar';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Events');
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('calendar', $user)) {
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
		$formattedResults = \array_map(function(array $eventRow) use ($calendarsById, $subscriptionsById):EventsSearchResultEntry {
			$thumbnailUrl = $this->urlGenerator->imagePath('calendar', 'calendar.svg');

			$component = $this->getPrimaryComponent($eventRow['calendardata'], self::$componentType);
			$title = (string)$component->SUMMARY ?? $this->l10n->t('Untitled event');
			$subline = $this->generateSubline($component);

			if ($eventRow['calendartype'] === CalDavBackend::CALENDAR_TYPE_CALENDAR) {
				$calendar = $calendarsById[$eventRow['calendarid']];
			} else {
				$calendar = $subscriptionsById[$eventRow['calendarid']];
			}
			$resourceUrl = $this->getDeepLinkToCalendarApp($calendar['uri'], $eventRow['uri']);

			return new EventsSearchResultEntry($thumbnailUrl, $title, $subline, $resourceUrl);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$query->getCursor() + count($formattedResults)
		);
	}

	/**
	 * @param string $calendarUri
	 * @param string $eventUri
	 * @return string
	 */
	private function getDeepLinkToCalendarApp(string $calendarUri, string $eventUri): string {
//		return $this->urlGenerator->getAbsoluteURL(
//			$this->urlGenerator->linkToRoute('calendar.view.index')
//		);
		return '';
	}

	private function generateSubline(Component $eventComponent): string {
		// TODO
		// If the event is timed and ends on the same day, display: 20.08.20 15:00 - 17:00
		// If the event is timed, but ends on a different day: 20.08.20 15 - 21.08.20 17:00
		// If the event is all-day and one day, display: 25.08.20
		// Otherwise: 25.08.20 - 28.08.20
		return '';
	}
}
