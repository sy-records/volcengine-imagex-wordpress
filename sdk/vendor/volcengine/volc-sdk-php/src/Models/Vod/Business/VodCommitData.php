<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: vod/business/vod_upload.proto

namespace Volc\Models\Vod\Business;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Volcengine.Models.Vod.Business.VodCommitData</code>
 */
class VodCommitData extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.Volcengine.Models.Vod.Business.VodCommitUploadInfoResponseData Data = 1;</code>
     */
    protected $Data = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Volc\Models\Vod\Business\VodCommitUploadInfoResponseData $Data
     * }
     */
    public function __construct($data = NULL) {
        \Volc\Models\Vod\GPBMetadata\VodUpload::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.Volcengine.Models.Vod.Business.VodCommitUploadInfoResponseData Data = 1;</code>
     * @return \Volc\Models\Vod\Business\VodCommitUploadInfoResponseData
     */
    public function getData()
    {
        return $this->Data;
    }

    /**
     * Generated from protobuf field <code>.Volcengine.Models.Vod.Business.VodCommitUploadInfoResponseData Data = 1;</code>
     * @param \Volc\Models\Vod\Business\VodCommitUploadInfoResponseData $var
     * @return $this
     */
    public function setData($var)
    {
        GPBUtil::checkMessage($var, \Volc\Models\Vod\Business\VodCommitUploadInfoResponseData::class);
        $this->Data = $var;

        return $this;
    }

}

