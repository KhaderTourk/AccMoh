
function topFunction() {
    document.body.scrollTop = 0; // For Safari
    document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
  }

  var windowOn = $(window);
  
  windowOn.on('load', function () {
    $("#loading").delay(200).fadeOut("slow");
  });

  windowOn.scroll(function () {
    if ($(this).scrollTop() > 300) {
      $('.sticky-top').addClass('shadow-sm').css('top', '0px');
    } else {
      $('.sticky-top').removeClass('shadow-sm').css('top', '-100px');
    }
  });
windowOn.scroll(function () {
  if ($(this).scrollTop() > 300) {
    $('.sticky-lg-top').css('top', '110px');
  } else {
    $('.sticky-lg-top').css('top', '0px');
  }
});
var loadFile = function(event) {
  var image = document.getElementById('output');
  image.src = URL.createObjectURL(event.target.files[0]);
};
var loadFile_2 = function(event) {
  var image = document.getElementById('output_2');
  image.src = URL.createObjectURL(event.target.files[0]);
};

document.addEventListener('DOMContentLoaded', function() {
  const cards = document.querySelectorAll('.work-card');
  let currentIndex = 0;
  let autoPlayInterval;

  function activateCard(index) {
    cards.forEach(card => card.classList.remove('active'));
    cards[index].classList.add('active');
  }

  function nextCard() {
    currentIndex = (currentIndex + 1) % cards.length; // التنقل اللا نهائي
    activateCard(currentIndex);
  }

  // بدء التشغيل التلقائي
  function startAutoPlay() {
    autoPlayInterval = setInterval(nextCard, 2000); // تغيير البطاقة كل 4 ثوانٍ
  }

  function stopAutoPlay() {
    clearInterval(autoPlayInterval);
  }

  // تفاعل عند مرور الماوس: إيقاف الموقت عند التأشير اليدوي
  cards.forEach((card, index) => {
    card.addEventListener('mouseenter', () => {
      stopAutoPlay();
      currentIndex = index;
      activateCard(index);
    });

    card.addEventListener('mouseleave', () => {
      startAutoPlay();
    });
  });

  startAutoPlay();
});
//