const addressData = require('china-area-data/v3/data');

// 引入 loadsh

import _ from 'lodash';

Vue.component('select-district', {
    // 定义组件的属性
    props: {
        // 初始化省市区的值，编辑的时候会用到
        initValue: {
            type: Array,
            default: () => ([]) // 默认是空数组
        },
    },
    // 定义这个组件内的数据
    data () {
        return {
            provinces: addressData[86],
            cities: {},
            districts: {},
            provinceId: '', // 当前选中的省id
            cityId: '', // 当前选中的市id
            districtId: '', // 当前选中的区id
        };
    },
    // 定义观察器，对应属性变更时会触发对应的函数
    watch: {
        // 当选择的省发生变化时触发
        provinceId(newVal) {
            console.log('new province' + newVal);
            if (!newVal) {
                this.cities = {};
                this.cityId = '';
                return;
            }
            // 将城市列表设为当前省下的城市
            this.cities = addressData[newVal];
            console.log(this.cities);
            // 如果当前选中的城市不在当前省下，则将选中城市清空
            if (!this.cities[this.cityId]) {
                this.cityId = '';
            }
        },
        // 当选择的市发生变化时触发
        cityId(newVal) {
            if (!newVal) {
                this.districts = {};
                this.districtId = '';
                return;
            }
            // 将区列表设为当前市下的区
            this.districts = addressData[newVal];
            // 如果选中的区不在当前市下，则将选中的区清空
            if (!this.districts[this.districtId]) {
                this.districtId = '';
            }
        },
        districtId() {
            // 触发一个 change 的vue事件，事件的值就是当前选中的省市区名称，格式为数组
            this.$emit('change', [this.provinces[this.provinceId], this.cities[this.cityId], this.districts[this.districtId]]);
        }
    },
    // 组件初始化时调用
    created() {
        this.setFromValue(this.initValue);
    },
    methods: {
        setFromValue(value) {
            console.log(value);
            // 过滤空值
            value = _.filter(value);
            // 如果长度为0，则将省清空（由于定义了观察器，会联动触发将城市和地区清空）
            if (value.length === 0) {
                this.provinceId = '';
                return;
            }
            // 从当前省列表中找到与数组第一个元素同名的项的索引
            const provinceId = _.findKey(this.provinces, o => o === value[0]);
            // 没找到清空省份的值
            if (!provinceId) {
                this.provinceId = '';
                return;
            }
            // 找到了将当前省设置成对应的id
            this.provinceId = provinceId;
            // 由于观察器的作用，此时城市列表已联动到对应省份的城市列表
            // 从当前市列表中找到与数组第二个元素同名的项的索引
            const cityId = _.findKey(addressData[provinceId], o => o === value[1]);
            if (!cityId) {
                this.cityId = '';
                return;
            }
            // 找到了将当前市设置成对应的id
            this.cityId = cityId;
            // 由于观察器的作用，此时区列表已联动到对应城市的区列表
            // 从当前区列表找到与数组第三个元素同名的项的索引
            const districtId = _.findKey(addressData[cityId], o => o === value[2]);
            //没找到，清空地区的值
            if (!districtId) {
                this.districtId = '';
                return;
            }
            // 找到了，将当前区设置为对应的id
            this.districtId = districtId;
        }
    }
});
