<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author René Gieling <github@dartcafe.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

 namespace OCA\Polls\Service;

 use Exception;
 use OCP\AppFramework\Db\DoesNotExistException;
 use OCA\Polls\Exceptions\EmptyTitleException;
 use OCA\Polls\Exceptions\InvalidAccessException;
 use OCA\Polls\Exceptions\InvalidShowResultsException;
 use OCA\Polls\Exceptions\InvalidPollTypeException;
 use OCA\Polls\Exceptions\NotAuthorizedException;

 use OCP\ILogger;

 use OCA\Polls\Db\Poll;
 use OCA\Polls\Db\PollMapper;
 use OCA\Polls\Service\CommentService;
 use OCA\Polls\Service\OptionService;
 use OCA\Polls\Service\ShareService;
 use OCA\Polls\Service\VoteService;
 use OCA\Polls\Service\LogService;
 use OCA\Polls\Model\Acl;

 class PollService {

	private $logger;
	private $pollMapper;
 	private $poll;
 	private $logService;
 	private $commentService;
 	private $optionService;
 	private $shareService;
 	private $voteService;
 	private $acl;

 	/**
 	 * PollController constructor.
 	 * @param PollMapper $pollMapper
 	 * @param LogService $logService
 	 * @param CommentService $commentService
 	 * @param OptionService $optionService
 	 * @param ShareService $shareService
 	 * @param VoteService $voteService
 	 * @param Acl $acl
 	 */

 	public function __construct(
		ILogger $logger,
 		PollMapper $pollMapper,
 		Poll $poll,
 		LogService $logService,
		CommentService $commentService,
		OptionService $optionService,
		ShareService $shareService,
		VoteService $voteService,
 		Acl $acl
 	) {
		$this->logger = $logger;
 		$this->pollMapper = $pollMapper;
 		$this->poll = $poll;
 		$this->logService = $logService;
 		$this->commentService = $commentService;
 		$this->optionService = $optionService;
 		$this->shareService = $shareService;
 		$this->voteService = $voteService;
 		$this->acl = $acl;
 	}


	/**
	 * list
	 * @NoAdminRequired
	 * @return array
	 */

	public function list() {
		if (!\OC::$server->getUserSession()->isLoggedIn()) {
			throw new NotAuthorizedException;
		}

		$pollList = [];

		$polls = $this->pollMapper->findAll();
		// TODO: Not the elegant way. Improvement neccessary
		foreach ($polls as $poll) {
			$combinedPoll = (object) array_merge(
				(array) json_decode(json_encode($poll)), (array) json_decode(json_encode($this->acl->setPollId($poll->getId()))));
			if ($combinedPoll->allowView) {
				$pollList[] = $combinedPoll;
			}
		}

		return $pollList;
	}

	/**
	 * get
	 * @NoAdminRequired
	 * @param integer $pollId
	 * @return array
	 */
 	public function get($pollId = 0, $token = '') {

		if (!$this->acl->setPollIdOrToken($pollId, $token)->getAllowView()) {
			throw new NotAuthorizedException;
		}

		$this->poll = $this->pollMapper->find($this->acl->getPollId());

		try {
			$comments = $this->commentService->list($this->poll->getId(), $token);
		} catch (Exception $e) {
			$comments = [];
		}

		try {
			$options = $this->optionService->list($this->poll->getId(), $token);
		} catch (Exception $e) {
			$options = [];

		}

		try {
			$votes = $this->voteService->list($this->poll->getId(), $token);
		} catch (Exception $e) {
			$votes = [];
		}

		try {
			$shares = $this->shareService->list($this->poll->getId());
		} catch (Exception $e) {
			$shares = [];
		}

		return [
			'acl' => $this->acl,
			'poll' => $this->poll,
			'comments' => $comments,
			'options' => $options,
			'shares' => $shares,
			'votes' => $votes
		];
 	}

	/**
	 * delete
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param integer $pollId
	 * @return Poll
	 */

	public function delete($pollId) {
		$this->poll = $this->pollMapper->find($pollId);

		if (!$this->acl->setPollId($pollId)->getAllowEdit()) {
			throw new NotAuthorizedException;
		}

		if ($this->poll->getDeleted()) {
			$this->poll->setDeleted(0);
		} else {
			$this->poll->setDeleted(time());
		}

		$this->poll = $this->pollMapper->update($this->poll);
		$this->logService->setLog($this->poll->getId(), 'deletePoll');

		return $this->poll;
	}

	/**
	 * deletePermanently
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param integer $pollId
	 * @return Poll
	 */

	public function deletePermanently($pollId) {
		$this->poll = $this->pollMapper->find($pollId);

		if (!$this->acl->setPollId($pollId)->getAllowEdit() || !$this->poll->getDeleted()) {
			throw new NotAuthorizedException;
		}

		return $this->pollMapper->delete($this->poll);
	}

	/**
	 * write
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param Array $poll
	 * @return Poll
	 */

	public function add($type, $title) {
		if (!\OC::$server->getUserSession()->isLoggedIn()) {
			throw new NotAuthorizedException;
		}

		// Validate valuess
		if (!in_array($type, $this->getValidPollType())) {
			throw new InvalidPollTypeException('Invalid poll type');
		}

		if (!$title) {
			throw new EmptyTitleException('Title must not be empty');
		}

		$this->poll = new Poll();
		$this->poll->setType($type);
		$this->poll->setCreated(time());
		$this->poll->setOwner($this->userId);
		$this->poll->setTitle($title);
		$this->poll->setDescription('');
		$this->poll->setAccess('hidden');
		$this->poll->setExpire(0);
		$this->poll->setAnonymous(0);
		$this->poll->setFullAnonymous(0);
		$this->poll->setAllowMaybe(0);
		$this->poll->setVoteLimit(0);
		$this->poll->setSettings('');
		$this->poll->setOptions('');
		$this->poll->setShowResults('always');
		$this->poll->setDeleted(0);
		$this->poll->setAdminAccess(0);
		$this->poll = $this->pollMapper->insert($this->poll);

		$this->logService->setLog($this->poll->getId(), 'addPoll');

		return $this->poll;
	}

	/**
	 * update
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param Array $poll
	 * @return Poll
	 */

	public function update($pollId, $poll) {

		$this->poll = $this->pollMapper->find($pollId);

		if (!$this->acl->setPollId($this->poll->getId())->getAllowEdit()) {
			throw new NotAuthorizedException;
		}

		// Validate valuess
		if (isset($poll['showResults']) && !in_array($poll['showResults'], $this->getValidShowResults())) {
			throw new InvalidShowResultsException('Invalid value for prop showResults');
		}

		if (isset($poll['access']) && !in_array($poll['access'], $this->getValidAccess())) {
			throw new InvalidAccessException('Invalid value for prop access '. $poll['access']);
		}

		if (isset($poll['title']) && !$poll['title']) {
			throw new EmptyTitleException('Title must not be empty');
		}

		$this->poll->setTitle($poll['title'] ? $poll['title'] : $this->poll->getTitle());
		$this->poll->setDescription(isset($poll['description']) ? $poll['description'] : $this->poll->getDescription());
		$this->poll->setAccess(isset($poll['access']) ? $poll['access'] : $this->poll->getAccess());
		$this->poll->setExpire(isset($poll['expire']) ? $poll['expire'] : $this->poll->getExpire());
		$this->poll->setAnonymous(isset($poll['anonymous']) ? $poll['anonymous'] : $this->poll->getAnonymous());
		$this->poll->setAllowMaybe(isset($poll['allowMaybe']) ? $poll['allowMaybe'] : $this->poll->getAllowMaybe());
		$this->poll->setVoteLimit(isset($poll['voteLimit']) ? $poll['voteLimit'] : $this->poll->getVoteLimit());
		$this->poll->setShowResults(isset($poll['showResults']) ? $poll['showResults'] : $this->poll->getShowResults());
		$this->poll->setDeleted(isset($poll['deleted']) ? $poll['deleted'] : $this->poll->getDeleted());
		$this->poll->setAdminAccess(isset($poll['adminAccess']) ? $poll['adminAccess'] : $this->poll->getAdminAccess());

		$this->pollMapper->update($this->poll);
		$this->logService->setLog($this->poll->getId(), 'updatePoll');

		return $this->poll;
	}

	/**
	 * clone
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param integer $pollId
	 * @return Poll
	 */
	public function clone($pollId) {

		if (!$this->acl->setPollId($this->poll->getId())->getAllowView()) {
			throw new NotAuthorizedException;
		}

		$this->poll = $this->pollMapper->find($pollId);

		$this->poll->setCreated(time());
		$this->poll->setOwner($this->userId);
		$this->poll->setTitle('Clone of ' . $this->poll->getTitle());
		$this->poll->setDeleted(0);
		$this->poll->setId(0);

		$this->poll = $this->pollMapper->insert($this->poll);
		$this->logService->setLog($this->poll->getId(), 'addPoll');

		$this->optionService->clone($pollId, $this->poll->getId());

		return $this->poll;

	}

	public function getValidEnum() {
		return [
			'pollType' => $this->getValidPollType(),
			'access' => $this->getValidAccess(),
			'showResults' => $this->getValidShowResults()
		];
	}

	private function getValidPollType() {
		return ['datePoll', 'textPoll'];
	}

	private function getValidAccess() {
		return ['hidden', 'public'];
	}

	private function getValidShowResults() {
		return ['always', 'expired', 'never'];
	}
}
