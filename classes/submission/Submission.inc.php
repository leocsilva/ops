<?php

/**
 * @defgroup submission Submission
 * Preprints, OMP's extension of the generic Submission class in lib-pkp, are
 * implemented here.
 */

/**
 * @file classes/submission/Submission.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Submission
 * @ingroup submission
 *
 * @see DAO
 *
 * @brief Preprint class.
 */

namespace APP\submission;

// Author display in ToC
define('AUTHOR_TOC_DEFAULT', 0);
define('AUTHOR_TOC_HIDE', 1);
define('AUTHOR_TOC_SHOW', 2);

// Preprint access constants -- see Publication::getData('accessStatus')
define('PREPRINT_ACCESS_OPEN', 1);

use APP\core\Application;
use APP\core\Services;

use PKP\facades\Locale;
use PKP\plugins\HookRegistry;
use PKP\submission\PKPSubmission;

class Submission extends PKPSubmission
{
    //
    // Get/set methods
    //

    /**
     * Get the value of a license field from the containing context.
     *
     * @param string $locale Locale code
     * @param int $field PERMISSIONS_FIELD_...
     * @param Publication $publication
     *
     * @return string|array|null
     */
    public function _getContextLicenseFieldValue($locale, $field, $publication = null)
    {
        $context = Services::get('context')->get($this->getData('contextId'));
        $fieldValue = null; // Scrutinizer
        switch ($field) {
            case PERMISSIONS_FIELD_LICENSE_URL:
                $fieldValue = $context->getData('licenseUrl');
                break;
            case PERMISSIONS_FIELD_COPYRIGHT_HOLDER:
                switch ($context->getData('copyrightHolderType')) {
                    case 'author':
                        $fieldValue = [$context->getPrimaryLocale() => $this->getAuthorString()];
                        break;
                    case 'context':
                    case null:
                        $fieldValue = $context->getName(null);
                        break;
                    default:
                        $fieldValue = $context->getData('copyrightHolderOther');
                        break;
                }
                break;
            case PERMISSIONS_FIELD_COPYRIGHT_YEAR:
                // Default copyright year to current year
                $fieldValue = date('Y');

                // Use preprint publish date of current publication
                if (!$publication) {
                    $publication = $this->getCurrentPublication();
                }
                if ($publication) {
                    $fieldValue = date('Y', strtotime($publication->getData('datePublished')));
                }
                break;
            default: assert(false);
        }

        // Return the fetched license field
        if ($locale === null) {
            return $fieldValue;
        }
        if (isset($fieldValue[$locale])) {
            return $fieldValue[$locale];
        }
        return null;
    }

    /**
     * @see PKPSubmission::getBestId()
     * @deprecated 3.2.0.0
     *
     * @return string
     */
    public function getBestPreprintId()
    {
        return parent::getBestId();
    }

    /**
     * Get ID of server.
     *
     * @deprecated 3.2.0.0
     *
     * @return int
     */
    public function getServerId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set ID of server.
     *
     * @deprecated 3.2.0.0
     *
     * @param int $serverId
     */
    public function setServerId($serverId)
    {
        return $this->setData('contextId', $serverId);
    }

    /**
     * Get ID of preprint's section.
     *
     * @return int
     */
    public function getSectionId()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return 0;
        }
        return $publication->getData('sectionId');
    }

    /**
     * Set ID of preprint's section.
     *
     * @param int $sectionId
     */
    public function setSectionId($sectionId)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('sectionId', $sectionId);
        }
    }

    /**
     * Get the localized cover page server-side file name
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedCoverImage()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        $coverImage = $publication->getLocalizedData('coverImage');
        return empty($coverImage['uploadName']) ? '' : $coverImage['uploadName'];
    }

    /**
     * get cover page server-side file name
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCoverImage($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        $coverImage = $publication->getData('coverImage', $locale);
        return empty($coverImage['uploadName']) ? '' : $coverImage['uploadName'];
    }

    /**
     * Get the localized cover page alternate text
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedCoverImageAltText()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        $coverImage = $publication->getLocalizedData('coverImage');
        return empty($coverImage['altText']) ? '' : $coverImage['altText'];
    }

    /**
     * get cover page alternate text
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCoverImageAltText($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        $coverImage = $publication->getData('coverImage', $locale);
        return empty($coverImage['altText']) ? '' : $coverImage['altText'];
    }

    /**
     * Get a full URL to the localized cover image
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedCoverImageUrl()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedCoverImageUrl($this->getData('contextId'));
    }

    /**
     * Get the galleys for an preprint.
     *
     * @return array PreprintGalley
     *
     * @deprecated 3.2.0.0
     */
    public function getGalleys()
    {
        $galleys = $this->getData('galleys');
        if (is_null($galleys)) {
            $this->setData('galleys', Application::get()->getRepresentationDAO()->getByPublicationId($this->getCurrentPublication()->getId(), $this->getData('contextId'))->toArray());
            return $this->getData('galleys');
        }
        return $galleys;
    }

    /**
     * Get the localized galleys for an preprint.
     *
     * @return array PreprintGalley
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedGalleys()
    {
        $allGalleys = $this->getData('galleys');
        $galleys = [];
        foreach ([Locale::getLocale(), Locale::getPrimaryLocale()] as $tryLocale) {
            foreach (array_keys($allGalleys) as $key) {
                if ($allGalleys[$key]->getLocale() == $tryLocale) {
                    $galleys[] = $allGalleys[$key];
                }
            }
            if (!empty($galleys)) {
                HookRegistry::call('PreprintGalleyDAO::getLocalizedGalleysByPreprint', [&$galleys]);
                return $galleys;
            }
        }

        return $galleys;
    }

    /**
     * Get total galley views for the preprint
     *
     * @return int
     */
    public function getTotalGalleyViews()
    {
        $application = Application::get();
        $publications = $this->getPublishedPublications();
        $views = 0;

        foreach ($publications as $publication) {
            foreach ((array) $publication->getData('galleys') as $galley) {
                $file = $galley->getFile();
                if (!$galley->getRemoteUrl() && $file) {
                    $views = $views + $application->getPrimaryMetricByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $file->getId());
                }
            }
        }
        return $views;
    }

    /**
     * Return option selection indicating if author should be hidden.
     *
     * @return int AUTHOR_TOC_...
     *
     * @deprecated 3.2.0.0
     */
    public function getHideAuthor()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return 0;
        }
        return $publication->getData('hideAuthor');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\submission\Submission', '\Submission');
}
