export default class DragScrollBehavior {
    /**
     * This method is used to table overflow horizontal-scrolling.
     * 
     * @function dragScroll
     * 
     * @param {*} elementSelector passed to ID or className
     */
    static dragScroll(elementSelector) {
        const element = document.querySelector(elementSelector); // This is to get element.
        let isDown = false;
        let startX;
        let scrollLeft;

        // if click on a page than dragScroll gets activated.
        element.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - element.offsetLeft;
            scrollLeft = element.scrollLeft;
        });

        // when leave the mouse button the dragScroll gets deactivated.
        element.addEventListener('mouseleave', () => {
            isDown = false;
        });

        // handled the mouse up event to deactivate the dragScroll
        element.addEventListener('mouseup', () => {
            isDown = false;
        });

        // handled mousemove event to activate the scroll
        element.addEventListener('mousemove', (e) => {
            if(!isDown) return;
            e.preventDefault();
            const positionX = e.pageX - element.offsetLeft;
            const walk = (positionX - startX); //scroll-fast
            element.scrollLeft = scrollLeft - walk;
        });
    }
}