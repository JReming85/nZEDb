<?php
namespace nzedb;

use nzedb\db\DB;

/**
 * Adds/fetches rar/zip/etc files for a release.
 *
 * Class ReleaseFiles
 */
class ReleaseFiles
{
	/**
	 * @var \nzedb\db\Settings
	 */
	protected $pdo;

	/**
	 * @var SphinxSearch
	 */
	public $sphinxSearch;

	/**
	 * @param \nzedb\db\Settings $settings
	 */
	public function __construct($settings = null)
	{
		$this->pdo = ($settings instanceof DB ? $settings : new DB());
		$this->sphinxSearch = new SphinxSearch();
	}

	/**
	 * Get all release files for a release ID.
	 *
	 * @param int $id The release ID.
	 *
	 * @return array
	 */
	public function get($id)
	{
		return $this->pdo->query(sprintf("SELECT * FROM release_files WHERE releases_id = %d ORDER BY release_files.name", $id));
	}

	/**
	 * Get all release files for a release GUID.
	 *
	 * @param string $guid The release GUID.
	 *
	 * @return array
	 */
	public function getByGuid($guid)
	{
		return $this->pdo->query(
			sprintf("
				SELECT release_files.*
				FROM release_files
				INNER JOIN releases r ON r.id = release_files.releases_id
				WHERE r.guid = %s
				ORDER BY release_files.name ",
				$this->pdo->escapeString($guid)
			)
		);
	}

	/**
	 * Delete release files for a release ID.
	 *
	 * @param int $id The release ID.
	 *
	 * @return mixed
	 */
	public function delete($id)
	{
		$res = $this->pdo->queryExec(sprintf("DELETE FROM release_files WHERE releases_id = %d", $id));
		$this->sphinxSearch->updateRelease($id, $this->pdo);
		return $res;
	}

	/**
	 * Add new files for a release ID.
	 *
	 * @param int    $id          The ID of the release.
	 * @param string $name        Name of the file.
	 * @param int    $size        Size of the file.
	 * @param int    $createdTime Unix time the file was created.
	 * @param int    $hasPassword Does it have a password (see Releases class constants)?
	 *
	 * @return mixed
	 */
	public function add($id, $name, $size, $createdTime, $hasPassword)
	{
		$insert = 0;

		$duplicateCheck = $this->pdo->queryOneRow(
			sprintf('
				SELECT releases_id
				FROM release_files
				WHERE releases_id = %d AND name = %s',
				$id,
				$this->pdo->escapeString(utf8_encode($name))
			)
		);

		if ($duplicateCheck === false) {
			$insert = $this->pdo->queryInsert(
					sprintf("
						INSERT INTO release_files
						(releases_id, name, size, createddate, passworded)
						VALUES
						(%d, %s, %s, %s, %d)",
						$id,
						$this->pdo->escapeString(utf8_encode($name)),
						$this->pdo->escapeString($size),
						$this->pdo->from_unixtime($createdTime),
						$hasPassword
					)
			);
			$this->sphinxSearch->updateRelease($id, $this->pdo);
		}
		return $insert;
	}
}
