<?php
namespace SPF\Component;

abstract class Observer implements \SplSubject
{
    /**
     * @var \SplObjectStorage
     */
    protected $_observers;

    /**
     * 添加观察者
     * @param \SplObserver $observer
     */
    public function attach(\SplObserver $observer)
    {
        if ($this->_observers == null) {
            $this->_observers = new \SplObjectStorage();
        }
        $this->_observers->attach($observer);
    }

    /**
     * 移除观察者
     * @param \SplObserver $observer
     */
    public function detach(\SplObserver $observer)
    {
        if ($this->_observers == null) {
            return;
        }
        $this->_observers->detach($observer);
    }

    /**
     * 通知所有观察者
     */
    public function notify()
    {
        if ($this->_observers == null) {
            return;
        }
        foreach ($this->_observers as $observer) {
            /**
             * @var  $observer \SplObserver
             */
            $observer->update($this);
        }
    }
}
