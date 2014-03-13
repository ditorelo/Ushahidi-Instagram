<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Facebook Controller
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   Ushahidi Team <team@ushahidi.com>
 * @package	   Ushahidi - http://source.ushahididev.com
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license	   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL)
*/

class Socialmedia_Instagram_Controller extends Controller
{
	var $service = null;

	public function __construct() {
		$this->service = ORM::factory("Service")
								->where("service_name", "SocialMedia Instagram")
								->find();
	}

	/**
	* Search function for Instagram
	* @param array $keywords Keyworkds for search
	* @param array[lat,lon,radius] $location Array with Geo point and radius to constrain search results
	* @param string $since yyyy-mm-dd Date to be used as since date on search
	*/
	public function search($keywords, $location, $since)
	{

		require_once dirname(__DIR__) . '/libraries/Instagram-PHP-API-master/instagram.class.php';

		$auth_config = array(
		    'apiKey'        => socialmedia_helper::getSetting('instagram_client_id'),
		    'apiSecret'		=> socialmedia_helper::getSetting('instagram_client_secret'),
		    'apiCallback'	=> null
		);

		$instagram = new Instagram($auth_config);

		foreach ($keywords as $keyword)
		{
			$keyword = str_replace("#", "", $keyword); //instagram doesn't like API calls with hash

			$settings = ORM::factory('socialmedia_settings')->where('setting', 'instagram_min_id_tag_' . $keyword)->find();

			$next_min_id = null;
			if (! empty($settings->value)) {
				$next_min_id = $settings->value;
			}

		    $results = $instagram->getTagMedia($keyword, 0, null, $next_min_id);

		    if ($results !== null)
		    {

				// parse our lovely results
				$result = $this->parse($results);


				// Save new highest id

				if (isset($results->pagination->next_min_id)) {
					$settings->setting =  'instagram_min_id_tag_' . $keyword;
					$settings->value = $results->pagination->next_min_id;
					$settings->save();
				}
			}
		}

	}



	/**
	* Parses API results and inserts them on the database
	* @param array $array_result json arrayed result
	* @param int $highest_id Current highest message ID on the database
	*/
	public function parse($array_result) {
		$statuses = $array_result->data;

		foreach ($statuses as $s) {
			$entry = Socialmedia_Message_Model::getMessage($s->id, $this->service->id);

			// don't resave messages we already have
			if (! $entry->loaded)
			{
				if ( ! is_null($s->caption))
				{
					$message = $s->caption->text;
				}
				else
				{
					$message = "[" . Kohana::lang('instagram.empty') . "]";
				}

				// set message data
				$entry->setServiceId($this->service->id);
				$entry->setMessageFrom($this->service->service_name);
				$entry->setMessageLevel($entry::STATUS_TOREVIEW);
				$entry->setMessageId($s->id);
				$entry->setMessageDetail($message);
				$entry->setMessageDate(date("Y-m-d H:i:s", $s->created_time));

				$entry->setAuthor(
					$s->user->id,
					$s->user->full_name,
					null,
					$s->user->username
				);

				// saves entities in array for later
				$media = array();
				$media["photo"][] = $s->images->standard_resolution->url;

				// geo data
				if (isset($s->location))
				{
					if (!empty($s->location->latitude))
					{
						$entry->setCoordinates($s->location->latitude, $s->location->longitude);
					}
				}

				// save message and assign data to it
				$entry->save();

				$entry->addData("url", $s->link);
				$entry->addAssets($media);
			}
		}

		return null;
	}
}
