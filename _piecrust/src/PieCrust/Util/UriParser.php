<?php

namespace PieCrust\Util;

use PieCrust\IPage;
use PieCrust\IPieCrust;
use PieCrust\PieCrustDefaults;
use PieCrust\PieCrustException;
use PieCrust\IO\FileSystem;


/**
 * A utility class for parsing page URIs.
 */
class UriParser
{
    /**
     * Parse a relative URI and returns information about it.
     */
    public static function parseUri(IPieCrust $pieCrust, $uri)
    {
        if (strpos($uri, '..') !== false)   // Some bad boy's trying to access files outside of our standard folders...
        {
            throw new PieCrustException('404');
        }
        
        $uri = trim($uri, '/');
        if ($uri == '') $uri = PieCrustDefaults::INDEX_PAGE_NAME;
        
        $pageNumber = 1;
        $matches = array();
        if (preg_match('/\/(\d+)\/?$/', $uri, $matches))
        {
            // Requesting a page other than the first for this article.
            $uri = substr($uri, 0, strlen($uri) - strlen($matches[0]));
            $pageNumber = intval($matches[1]);
        }
        
        $pageInfo = array(
                'uri' => $uri,
                'page' => $pageNumber,
                'type' => IPage::TYPE_REGULAR,
                'blogKey' => null,
                'key' => null,
                'date' => null,
                'path' => null,
                'was_path_checked' => false
            );
        
        // Try first with a regular page path.
        if (UriParser::tryParsePageUri($pieCrust, $uri, $pageInfo))
        {
            return $pageInfo;
        }
        
        $blogKeys = $pieCrust->getConfig()->getValueUnchecked('site/blogs');
        
        // Try with a post.
        foreach ($blogKeys as $blogKey)
        {
            if (UriParser::tryParsePostUri($pieCrust, $blogKey, $uri, $pageInfo))
            {
                return $pageInfo;
            }
        }
        
        // Try with special pages (tag & category)
        foreach ($blogKeys as $blogKey)
        {
            if (UriParser::tryParseTagUri($pieCrust, $blogKey, $uri, $pageInfo))
            {
                return $pageInfo;
            }
            if (UriParser::tryParseCategoryUri($pieCrust, $blogKey, $uri, $pageInfo))
            {
                return $pageInfo;
            }
        }
        
        // No idea what that URI is...
        return null;
    }
    
    private static function tryParsePageUri(IPieCrust $pieCrust, $uri, array &$pageInfo)
    {
        $pagesDir = $pieCrust->getPagesDir();
        if ($pagesDir === false)
            return false;

        $matches = array();
        $uriWithoutExtension = $uri;
        if (preg_match('/\.[a-zA-Z0-9]+$/', $uri, $matches))
        {
            // There's an extension specified. Strip it
            // (the extension is probably because the page has a `content_type` different than HTML, which means
            //  it would be baked into a static file with that extension).
            $uriWithoutExtension = substr($uri, 0, strlen($uri) - strlen($matches[0]));
        }
        
        $path = $pagesDir . $uriWithoutExtension . '.html';
        if (is_file($path))
        {
            $pageInfo['path'] = $path;
            $pageInfo['was_path_checked'] = true;
            return true;
        }
        return false;
    }
    
    private static function tryParsePostUri(IPieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $postsDir = $pieCrust->getPostsDir();
        if ($postsDir === false)
            return false;

        $matches = array();
        $postsPattern = UriBuilder::buildPostUriPattern($pieCrust->getConfig()->getValueUnchecked($blogKey.'/post_url'));
        if (preg_match($postsPattern, $uri, $matches))
        {
            $fs = FileSystem::create($pieCrust, $blogKey);
            $pathInfo = $fs->getPathInfo($matches);
            $date = mktime(0, 0, 0, intval($pathInfo['month']), intval($pathInfo['day']), intval($pathInfo['year']));
            
            $pageInfo['type'] = IPage::TYPE_POST;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['date'] = $date;
            $pageInfo['path'] = $pathInfo['path'];
            return true;
        }
        return false;
    }
    
    private static function tryParseTagUri(IPieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $pagesDir = $pieCrust->getPagesDir();
        if ($pagesDir === false)
            return false;

        $matches = array();
        $tagsPattern = UriBuilder::buildTagUriPattern($pieCrust->getConfig()->getValueUnchecked($blogKey.'/tag_url'));
        if (preg_match($tagsPattern, $uri, $matches))
        {
            $prefix = '';
            if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
                $prefix = $blogKey . '/';
            
            $path = $pagesDir . $prefix . PieCrustDefaults::TAG_PAGE_NAME . '.html';
            
            $tags = explode('/', trim($matches['tag'], '/'));
            if (count($tags) > 1)
            {
                // Check the tags were specified in alphabetical order.
                //TODO: temporary check until I find a way to make it cheap to support all permutations in the baker.
                sort($tags);
                if (implode('/', $tags) != $matches['tag'])
                    throw new PieCrustException("Multi-tags must be specified in alphabetical order, sorry.");
            }
            else
            {
                $tags = $matches['tag'];
            }
            
            $pageInfo['type'] = IPage::TYPE_TAG;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['key'] = $tags;
            $pageInfo['path'] = $path;
            
            return true;
        }
        return false;
    }
    
    private static function tryParseCategoryUri(IPieCrust $pieCrust, $blogKey, $uri, array &$pageInfo)
    {
        $pagesDir = $pieCrust->getPagesDir();
        if ($pagesDir === false)
            return false;

        $categoryPattern = UriBuilder::buildCategoryUriPattern($pieCrust->getConfig()->getValueUnchecked($blogKey.'/category_url'));
        if (preg_match($categoryPattern, $uri, $matches))
        {
            $prefix = '';
            if ($blogKey != PieCrustDefaults::DEFAULT_BLOG_KEY)
                $prefix = $blogKey . '/';
            
            $path = $pagesDir . $prefix . PieCrustDefaults::CATEGORY_PAGE_NAME . '.html';
            
            $pageInfo['type'] = IPage::TYPE_CATEGORY;
            $pageInfo['blogKey'] = $blogKey;
            $pageInfo['key'] = $matches['cat'];
            $pageInfo['path'] = $path;
            return true;
        }
        return false;
    }
}
