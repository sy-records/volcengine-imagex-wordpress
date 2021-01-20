<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: vod/business/vod_workflow.proto

namespace Volc\Models\Vod\Business;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Volcengine.Models.Vod.Business.LogoOverride</code>
 */
class LogoOverride extends \Google\Protobuf\Internal\Message
{
    /**
     * 被覆盖的水印模板Id, 支持ALL
     *
     * Generated from protobuf field <code>string TemplateId = 1;</code>
     */
    protected $TemplateId = '';
    /**
     * 自定义水印变量
     *
     * Generated from protobuf field <code>map<string, string> Vars = 2;</code>
     */
    private $Vars;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $TemplateId
     *           被覆盖的水印模板Id, 支持ALL
     *     @type array|\Google\Protobuf\Internal\MapField $Vars
     *           自定义水印变量
     * }
     */
    public function __construct($data = NULL) {
        \Volc\Models\Vod\GPBMetadata\VodWorkflow::initOnce();
        parent::__construct($data);
    }

    /**
     * 被覆盖的水印模板Id, 支持ALL
     *
     * Generated from protobuf field <code>string TemplateId = 1;</code>
     * @return string
     */
    public function getTemplateId()
    {
        return $this->TemplateId;
    }

    /**
     * 被覆盖的水印模板Id, 支持ALL
     *
     * Generated from protobuf field <code>string TemplateId = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setTemplateId($var)
    {
        GPBUtil::checkString($var, True);
        $this->TemplateId = $var;

        return $this;
    }

    /**
     * 自定义水印变量
     *
     * Generated from protobuf field <code>map<string, string> Vars = 2;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getVars()
    {
        return $this->Vars;
    }

    /**
     * 自定义水印变量
     *
     * Generated from protobuf field <code>map<string, string> Vars = 2;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setVars($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::STRING);
        $this->Vars = $arr;

        return $this;
    }

}
