<?php
namespace Megoc\Ecjtu\CodeOCR;

/**
 * Ecjtu 教务系统验证码识别
 * 
 * Date： 2018年6月10日
 * 
 */
class EcjtuOCR
{
    public $result;
    // image information
    protected $image_info;
    // image word string
    protected $image_word_string;
    // library file path
    protected $code_jwxt_library_path;
    // image array
    protected $image_array;
    // image signal word array
    protected $image_word_array;
    // image binary bit stream
    protected $image_bit;


    public function __construct($image_bit, $code_jwxt_library_path='')
    {
        if (empty($code_jwxt_library_path)) {
            $this->code_jwxt_library_path = __DIR__ . '/code_library.json';
        }

        $this->image($image_bit);

        // $this->output($this->image_word_array[0]);
        // $this->output($this->image_word_array[1]);
        // $this->output($this->image_word_array[2]);
        // $this->output($this->image_word_array[3]);
    }
    /**
     * init function
     *
     * @return void
     */
    protected function init()
    {
        if (empty($this->image_bit)) {
            return '';
        }

        $this->get_image_info();
        $this->get_image_array();
        $this->divide_word_array();
        $this->remove_noise_point();
        $this->word_array_to_string();
    }

    public function image($image_bit)
    {
        // 判断传入的是文件路径还是二进制流图片
        if (!@getimagesizefromstring($image_bit) && file_exists($image_bit)) {
            $this->image_bit = file_get_contents($image_bit);
        } else {
            $this->image_bit = $image_bit;
        }

        $this->init();
        return $this;
    }

    public function result()
    {
        $this->word_string_compare();

        if (!empty($this->result)) {
            return join('', $this->result);
        } else {
            return '';
        }
    }
    public function learn(Type $var = null)
    {
        # code...
    }
    /**
     * 输出显示矩阵
     *
     * @param Type $var
     * @return void
     */
    public function output($array=[])
    {
        if (empty($array)) {
            $array = $this->image_array;
        } else {
            foreach ($array as $key => $value) {
                foreach ($value as $k => $v) {
                    if (is_array($v)) {
                        $array = $this->image_array;
                    }
                }
            }
        }

        echo "<pre>";
        foreach ($array as $key => $value) {
            foreach ($value as $k => $v) {
                echo $v;
            }
            echo "\n";
        }
    }
    /**
     * 获取图片信息
     *
     * @return void
     */
    protected function get_image_info()
    {
        // 从图片流获取图片信息
        $info = @getimagesizefromstring($this->image_bit);

        $ext_map = [0=>'UNKNOWN',1=>'GIF',2=>'JPEG',3=>'PNG',4=>'SWF',5=>'PSD',6=>'BMP',7=>'TIFF_II',8=>'TIFF_MM',9=>'JPC',10=>'JP2',11=>'JPX',12=>'JB2',13=>'SWC',14=>'IFF',15=>'WBMP',16=>'XBM',17=>'ICO',18=>'COUNT'];

        if (!empty($info)) {
            $this->image_info = [
                'width'  => $info[0],
                'height' => $info[1],
                'ext'    => $ext_map[$info[2]],
                'bits'   => $info['bits'],
                'mime'   => $info['mime'],
            ];
        }

        return $this;
    }
    /**
     * 将图片二值化转为矩阵
     *
     * @param string $ponits
     * @return void
     */
    protected function get_image_array($ponits='')
    {
        if (empty($this->image_info['width'])) {
            return $this;
        }

        $image_resource = imagecreatefromstring($this->image_bit);

        $image_array = [];
        // 判断噪点信息设置是否为空
        if (empty($ponits)) {
            $ponits = [
                'red'   => 150,
                'blue'  => 200,
                'green' => 220,
            ];
        }
        // 如果传入的噪点信息不是数组
        if (!is_array($ponits)) {
            $ponit_tmp = $ponits;
            $ponits = [
                'red'   => $ponit_tmp,
                'blue'  => $ponit_tmp,
                'green' => $ponit_tmp,
            ];
        }

        for ($i=0; $i < $this->image_info['height']; $i++) {
            for ($j=0; $j < $this->image_info['width']; $j++) {

                if ($i < 5) {
                    $image_array[$i][$j] = 0;
                    continue;
                }

                if ($i > 27) {
                    $image_array[$i][$j] = 0;
                    continue;
                }

                $rgb = imagecolorat($image_resource, $j, $i);
                $rgb_index = imagecolorsforindex($image_resource, $rgb);

                if ($rgb_index['red'] < $ponits['red'] || $rgb_index['blue'] < $ponits['blue'] || $rgb_index['green'] < $ponits['green']) {
                    $image_array[$i][$j] = 1;
                } else {
                    $image_array[$i][$j] = 0;
                }
            }
        }

        $this->image_array = $image_array;
        return $this;
    }
    /**
     * 分割成单字矩阵
     *
     * @param integer $words
     * @return void
     */
    protected function divide_word_array($words=4)
    {
        if (empty($words) || !is_numeric($words)) {
            return $this;
        }

        $avrage_pixel = $this->image_info['width'] / $words;
        $word_array   = [];

        for ($i=0; $i < $words; $i++) { 
            for ($k=4; $k < $this->image_info['height'] - 7; $k++) { 
                for ($j=$i*$avrage_pixel; $j < ($i+1)*$avrage_pixel - 5; $j++) { 
                    $word_array[$i][$k][] = $this->image_array[$k][$j];
                }
            }
        }

        $this->image_word_array = $word_array;
        return $this;
    }
    /**
     * 消除噪点，结果保存在单字矩阵里
     *
     * @return void
     */
    protected function remove_noise_point()
    {
        for ($i=0; $i < count($this->image_word_array); $i++) { 
            // 消除前面四个像素点干扰
            for ($j=0; $j < 4; $j++) { 
                foreach ($this->image_word_array[$i] as $key => $value) {
                    $this->image_word_array[$i][$key][$j] = 0;
                }
            }
            // 消除后面像素点干扰
            for ($k=19; $k < count($this->image_word_array[$i]); $k++) { 
                foreach ($this->image_word_array[$i] as $key => $value) {
                    $this->image_word_array[$i][$key][$k] = 0;
                }
            }
        }

        return $this;
    }
    /**
     * 将单字矩阵连接为字符串
     *
     * @return void
     */
    protected function word_array_to_string()
    {
        $word_string = [];
        foreach ($this->image_word_array as $key => $value) {
            $string = '';
            foreach ($value as $k => $v) {
                $string .= join('', $v);
            }
            $word_string[$key] = $string;
        }

        $this->image_word_string = $word_string;
        return $this;
    }
    /**
     * 字符比较
     *
     * @return void
     */
    protected function word_string_compare()
    {
        if (empty($this->image_bit)) {
            return ;
        }

        $code_library = $this->load_library();

        if (empty($code_library)) {
            return ;
        }

        $result = [];

        foreach ($this->image_word_string as $ko => $vo) {

            foreach ($code_library as $key => $value) {
                $pers = [];
                // 遍历对比获取相似度
                foreach ($value as $v) {
                    similar_text($vo, $v, $pers[]);
                }
                $string_max_percent[$key] = is_array($pers) ? max($pers) : 0;
            }
            // 找出相似度最高对应的字符
            $result[$ko] = array_search(max($string_max_percent), $string_max_percent);
        }

        $this->result = $result;
        return $this;
    }
    /**
     * 加载比对特征库
     *
     * @return void
     */
    protected function load_library()
    {
        if (file_exists($this->code_jwxt_library_path)) {
            $file = file_get_contents($this->code_jwxt_library_path);
            $code_library = json_decode($file, true);
        } else {
            $code_library = [];
        }

        return $code_library;
    }
    /**
     * 学习并输出库文件
     *
     * @param [type] $images_dir
     * @param [type] $accuracy
     * @param [type] $out_file
     * @param boolean $debug
     * @return void
     */
    protected function learn_out_library($images_dir, $accuracy, $out_file, $debug=false)
    {
        if (!file_exists($images_dir)) {
            echo "本次学习了0个图片";
            return ;
        }

        $image_list   = $this->get_directory_file_list($images_dir);
        $code_library = [];
        $n = 0;
        foreach ($image_list as $k => $v) {
            $this->image_bit = file_get_contents($images_dir . '/' . $v);
            $this->get_image_info();
            $this->get_image_array();
            $this->divide_word_array();
            $this->remove_noise_point();
            $this->word_array_to_string();

            foreach ($this->image_word_string as $ko => $vo) {
                // 获取已有库的相似度
                $pers = [];

                $word = substr($k, $ko, 1);

                if (empty($code_library[$word])) {
                    $code_library[$word][] = $vo;
                    continue ;
                }

                foreach ($code_library[$word] as $key => $value) {
                    similar_text($vo, $value, $pers[]);
                }
                $string_max_percent = is_array($pers) ? max($pers) : 0;

                // 判断是否有满足精度要求的字符，否则入库
                if ($string_max_percent < $accuracy) {
                    $code_library[$word][] = $vo;
                }

            }
    
        }
        ksort($code_library);
        $this->output_array_to_file($out_file, $code_library);

        echo "本次共学习了" . count($image_list) . "个图片";
    }
    /**
     * 获取目录下的所有文件，以数组形式返回
     *
     * @param [type] $directory
     * @return void
     */
    protected function get_directory_file_list($directory)
    {
        $file_list = [];
        $resource  = opendir($directory);

        while ($row = readdir($resource)) {
            if ($row == '.' || $row == '..') continue;
            $file_list[explode('.', $row)[0]] = $row;
        }

        return $file_list;
    }
    /**
     * 输出库文件
     *
     * @param [type] $out_file
     * @param [type] $array
     * @return void
     */
    protected function output_array_to_file($out_file, $array)
    {
        if (empty($out_file)) {
            return ;
        }

        $string = json_encode($array);

        file_put_contents($out_file, $string);
        return ;
    }

}
